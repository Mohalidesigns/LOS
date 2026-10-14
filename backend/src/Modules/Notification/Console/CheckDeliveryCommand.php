<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Console;

use Fundly\Integration\Ports\Sms\SmsPort;
use Fundly\Integration\Ports\WhatsApp\WhatsAppPort;
use Fundly\Integration\Runtime\BindingResolver;
use Fundly\Integration\Runtime\Errors\IntegrationException;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Mail\MailManager;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/**
 * Operator check of the delivery channels without sending anything: logs in
 * to the SMTP relay (connect, TLS, AUTH, quit) and reports the SMS switch,
 * driver, credentials present and each tenant's sms binding. Secrets are
 * never printed.
 */
final class CheckDeliveryCommand extends Command
{
    protected $signature = 'notifications:check-delivery';

    protected $description = 'Check SMTP login and SMS configuration without sending a message.';

    public function handle(MailManager $mail, TenantDirectory $tenants, TenantContext $tenant, BindingResolver $bindings): int
    {
        $ok = true;
        $mailer = (string) config('mail.default');
        $smtp = (array) config('mail.mailers.smtp');
        $this->line("Mail: default mailer '{$mailer}'".(config('mail.always_to') ? ', redirected to MAIL_ALWAYS_TO' : ''));
        if ($mailer === 'smtp' || $this->confirmSmtpCheck($mailer)) {
            try {
                $transport = $mail->createSymfonyTransport($smtp + ['timeout' => 20]);
                if ($transport instanceof SmtpTransport) {
                    $transport->start();
                    $transport->stop();
                }
                $this->info("SMTP {$smtp['host']}:{$smtp['port']} ({$smtp['scheme']}): connected and authenticated.");
            } catch (Throwable $e) {
                $ok = false;
                $this->error('SMTP login failed: '.(string) preg_replace('/\S+@\S+/', '<address>', $e->getMessage()));
            }
        }

        $sms = (array) config('fundly.sms');
        $holla = (array) ($sms['hollatags'] ?? []);
        $this->line('SMS: '.(($sms['enabled'] ?? false) ? 'enabled' : 'disabled')." · driver '".($sms['driver'] ?? '')."' · HollaTags credentials ".(! empty($holla['username']) && ! empty($holla['password']) && ! empty($holla['sender']) ? 'present' : 'missing')
            .' · SMS_ALWAYS_TO '.(empty($sms['always_to']) ? 'not set' : 'set'));
        $wa = (array) config('fundly.whatsapp');
        $waHolla = (array) ($wa['hollatags'] ?? []);
        $this->line('WhatsApp: '.(($wa['enabled'] ?? false) ? 'enabled' : 'disabled')." · driver '".($wa['driver'] ?? '')."' · business number ".(empty($waHolla['from']) ? 'missing (WHATSAPP_FROM)' : 'set')
            .' · '.(($wa['replaces_sms'] ?? false) ? 'replaces SMS for opted-in customers' : 'alongside SMS').' · always-to '.(empty($wa['always_to']) ? 'not set' : 'set'));
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $tenant->run($tenantId, function () use ($bindings, $tenantId, &$ok): void {
                foreach ([SmsPort::PORT => 'integration:bind-sms', WhatsAppPort::PORT => 'integration:bind-whatsapp'] as $port => $fix) {
                    try {
                        $b = $bindings->resolve($port);
                        $this->line("  tenant {$tenantId}: {$port} → {$b->adapter_key}");
                    } catch (IntegrationException $e) {
                        $ok = false;
                        $this->warn("  tenant {$tenantId}: {$e->getMessage()} Run {$fix}.");
                    }
                }
            });
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function confirmSmtpCheck(string $mailer): bool
    {
        $this->line("  (mail is not sent over SMTP while MAIL_MAILER={$mailer}; checking the SMTP login anyway)");

        return ! empty(config('mail.mailers.smtp.host'));
    }
}
