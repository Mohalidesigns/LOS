<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Console;

use Illuminate\Console\Command;

/** DEV TOOL: generate an Ed25519 vendor keypair. Refuses to run in production. */
final class LicenceKeypairCommand extends Command
{
    protected $signature = 'licence:keypair {--secret-out= : Write the secret key (base64) to this file, mode 0400}';

    protected $description = '[dev only] Generate an Ed25519 licence signing keypair.';

    public function handle(): int
    {
        if (app()->isProduction() || config('fundly.installation.environment') === 'production') {
            $this->error('licence:keypair is a development tool and is disabled in production. The vendor signing key never lives on a bank installation.');

            return self::FAILURE;
        }
        $pair = sodium_crypto_sign_keypair();
        $public = base64_encode(sodium_crypto_sign_publickey($pair));
        $secret = base64_encode(sodium_crypto_sign_secretkey($pair));

        $out = $this->option('secret-out');
        if (is_string($out) && $out !== '') {
            file_put_contents($out, $secret);
            chmod($out, 0400);
            $this->info("Secret key written to {$out}");
        } else {
            $this->line("FUNDLY_LICENCE_SECRET_KEY={$secret}");
        }
        $this->line("FUNDLY_LICENCE_PUBLIC_KEY={$public}");

        return self::SUCCESS;
    }
}
