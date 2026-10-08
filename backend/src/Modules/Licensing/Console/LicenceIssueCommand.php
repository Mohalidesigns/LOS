<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Console;

use DateTimeImmutable;
use Fundly\Integration\Ports\Licensing\LicensingPort;
use Fundly\Integration\Ports\Licensing\SignedLicence;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Json\CanonicalJson;
use Illuminate\Console\Command;

/** DEV TOOL: mint a signed test licence (the real licences come from the vendor's licensing service). */
final class LicenceIssueCommand extends Command
{
    protected $signature = 'licence:issue
        {--secret-key-file= : File holding the base64 Ed25519 secret key}
        {--client=Development Bank : Licensee name}
        {--modules=core : Comma-separated entitled modules ("*" = all)}
        {--max-users=50 : Named-user cap}
        {--max-legal-entities=5}
        {--valid-from= : ISO date (default now)}
        {--valid-to= : ISO date (default +1 year)}
        {--grace-days=30}
        {--fingerprint= : Installation fingerprint (default: this installation)}
        {--out= : Output file (default: stdout)}';

    protected $description = '[dev only] Issue a signed licence file for testing.';

    public function handle(LicensingPort $port): int
    {
        if (app()->isProduction() || config('fundly.installation.environment') === 'production') {
            $this->error('licence:issue is a development tool and is disabled in production.');

            return self::FAILURE;
        }
        $file = $this->option('secret-key-file');
        $secret = is_string($file) && is_file($file) ? base64_decode(trim((string) file_get_contents($file)), true) : false;
        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            $this->error('Provide --secret-key-file with a base64 Ed25519 secret key (see licence:keypair).');

            return self::FAILURE;
        }
        $from = new DateTimeImmutable(is_string($this->option('valid-from')) ? $this->option('valid-from') : 'now');
        $to = new DateTimeImmutable(is_string($this->option('valid-to')) ? $this->option('valid-to') : '+1 year');
        $fingerprint = $this->option('fingerprint');
        $licence = [
            'licence_id' => 'LIC-'.UuidV7::generate(),
            'client' => (string) $this->option('client'),
            'installation_fingerprint' => is_string($fingerprint) && $fingerprint !== '' ? $fingerprint : $port->installationFingerprint(),
            'edition' => 'standard',
            'modules' => array_values(array_filter(array_map('trim', explode(',', (string) $this->option('modules'))))),
            'max_named_users' => (int) $this->option('max-users'),
            'max_legal_entities' => (int) $this->option('max-legal-entities'),
            'adapter_entitlements' => ['cba-simulator'],
            'valid_from' => $from->format(DATE_ATOM),
            'valid_to' => $to->format(DATE_ATOM),
            'grace_days' => (int) $this->option('grace-days'),
            'support_tier' => 'standard',
        ];
        $document = CanonicalJson::encode($licence);
        $signed = new SignedLicence($document, base64_encode(sodium_crypto_sign_detached($document, $secret)));

        $out = $this->option('out');
        if (is_string($out) && $out !== '') {
            file_put_contents($out, $signed->toFileContents());
            $this->info("Licence {$licence['licence_id']} written to {$out}");
        } else {
            $this->line($signed->toFileContents());
        }

        return self::SUCCESS;
    }
}
