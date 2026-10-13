<?php

declare(strict_types=1);

namespace Fundly\Integration\Adapters\Gotenberg;

use Fundly\Integration\Ports\PdfRender\PdfRenderPort;
use Fundly\Integration\Ports\PdfRender\RenderedPdf;
use Fundly\Integration\Runtime\CapabilityManifest;
use Fundly\Integration\Runtime\Errors\NonRetryableError;
use Fundly\Integration\Runtime\Errors\RetryableError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Bundled Gotenberg (Chromium) over the internal network. JavaScript is
 * disabled and only file:///tmp is allowed in the container, so a template
 * cannot fetch anything remote.
 */
final class GotenbergRenderer implements PdfRenderPort
{
    public const KEY = 'gotenberg';

    public const VERSION = '1.0.0';

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public static function capabilities(): CapabilityManifest
    {
        return new CapabilityManifest(self::KEY, self::VERSION, '1.0', 'on_prem:bundled', [
            self::OP_RENDER => ['support' => 'native', 'idempotency' => 'natural'],
        ]);
    }

    public function render(string $html): RenderedPdf
    {
        $url = rtrim(is_string($this->config['url'] ?? null) ? $this->config['url'] : 'http://gotenberg:3000', '/');
        try {
            $response = Http::timeout(30)->attach('files', $html, 'index.html')
                ->post($url.'/forms/chromium/convert/html', ['paperWidth' => '8.27', 'paperHeight' => '11.69', 'printBackground' => 'true']);
        } catch (ConnectionException $e) {
            throw new RetryableError('PDF.TRANSPORT.UNREACHABLE', 'Gotenberg is unreachable: '.$e->getMessage());
        }
        if ($response->serverError()) {
            throw new RetryableError('PDF.PROVIDER.ERROR', 'Gotenberg returned HTTP '.$response->status().'.');
        }
        if (! $response->successful() || ! str_starts_with($response->body(), '%PDF')) {
            throw new NonRetryableError('PDF.RENDER.REJECTED', 'Gotenberg could not render the document (HTTP '.$response->status().').');
        }

        return new RenderedPdf($response->body(), self::KEY.'/'.self::VERSION);
    }
}
