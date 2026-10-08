<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Bus\DomainEvent;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\OutboxIntent;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Security\AccessDenied;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\PrincipalKind;
use Fundly\Shared\Security\ResourceRef;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class ProbeEvent implements DomainEvent
{
    public function name(): string
    {
        return 'probe.happened';
    }

    public function payload(): array
    {
        return [];
    }
}

final class ProbeHandler implements CommandHandler
{
    public static array $calls = [];

    public function handle(Command $command, CommandContext $context): mixed
    {
        assert($command instanceof ProbeCommand);
        self::$calls[] = 'handle';
        DB::table('sod_rules')->insert(['id' => UuidV7::generate(), 'tenant_id' => $context->principal->tenantId, 'kind' => 'permission_pair', 'left_ref' => 'a:b', 'right_ref' => 'c:d', 'description' => 'probe', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $context->audit(new AuditEntry('probe.done', entityType: 'probe', entityId: 'p1'));
        $context->outbox(new OutboxIntent('probe.topic', ['x' => 1], idempotencyKey: 'probe-key-'.count(self::$calls)));
        $context->raise(new ProbeEvent);
        if ($command->explode) {
            throw new RuntimeException('handler failed');
        }

        return 'ok';
    }
}

#[HandledBy(ProbeHandler::class)]
final class ProbeCommand implements Command, ValidatesInput
{
    public function __construct(public bool $explode = false, public string $name = 'ok') {}

    public function action(): string
    {
        return 'probe.run';
    }

    public function permission(): ?string
    {
        return Permission::SodRuleManage->value;
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }

    public function data(): array
    {
        return ['name' => $this->name];
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:5']];
    }
}

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    ProbeHandler::$calls = [];
});

it('runs authorize → validate → handle → audit → outbox in one transaction and dispatches events after commit', function () {
    $u = $this->userWith([Permission::SodRuleManage]);
    $p = new Principal($u->id, $this->tenant->id, PrincipalKind::Human);
    Event::fake([ProbeEvent::class]);
    expect(app(CommandBus::class)->dispatch(new ProbeCommand, $p))->toBe('ok');
    expect(DB::table('audit_events')->where('action', 'probe.done')->where('actor_id', $u->id)->exists())->toBeTrue()
        ->and(DB::table('outbox_messages')->where('topic', 'probe.topic')->value('status'))->toBe('pending');
    Event::assertDispatched(ProbeEvent::class);
})->group('FR-CBA-008', 'FR-AUD-001');

it('rolls back state, audit and outbox together when the handler fails', function () {
    $u = $this->userWith([Permission::SodRuleManage]);
    $p = new Principal($u->id, $this->tenant->id, PrincipalKind::Human);
    Event::fake([ProbeEvent::class]);
    expect(fn () => app(CommandBus::class)->dispatch(new ProbeCommand(explode: true), $p))->toThrow(RuntimeException::class);
    expect(DB::table('sod_rules')->where('description', 'probe')->exists())->toBeFalse()
        ->and(DB::table('audit_events')->where('action', 'probe.done')->exists())->toBeFalse()
        ->and(DB::table('outbox_messages')->where('topic', 'probe.topic')->exists())->toBeFalse();
    Event::assertNotDispatched(ProbeEvent::class);
})->group('FR-CBA-008');

it('authorizes before validating and never reaches the handler when denied; the denial is audited', function () {
    $u = $this->userWith([]);
    $p = new Principal($u->id, $this->tenant->id, PrincipalKind::Human);
    expect(fn () => app(CommandBus::class)->dispatch(new ProbeCommand(name: 'way-too-long-name'), $p))->toThrow(AccessDenied::class);
    expect(ProbeHandler::$calls)->toBe([])
        ->and(DB::table('audit_events')->where('action', 'authz.denied')->where('actor_id', $u->id)->count())->toBe(1);
})->group('FR-AUD-007', 'FR-SEC-003');

it('validates before handling', function () {
    $u = $this->userWith([Permission::SodRuleManage]);
    expect(fn () => app(CommandBus::class)->dispatch(new ProbeCommand(name: 'way-too-long-name'), new Principal($u->id, $this->tenant->id, PrincipalKind::Human)))->toThrow(ValidationFailed::class);
    expect(ProbeHandler::$calls)->toBe([]);
});

it('refuses system-only commands to non-system principals', function () {
    $cmd = new class implements Command
    {
        public function action(): string
        {
            return 'sys.only';
        }

        public function permission(): ?string
        {
            return null;
        }

        public function resource(): ?ResourceRef
        {
            return null;
        }
    };
    $u = $this->userWith([]);
    expect(fn () => app(CommandBus::class)->dispatch($cmd, new Principal($u->id, $this->tenant->id, PrincipalKind::Human)))->toThrow(AccessDenied::class);
});
