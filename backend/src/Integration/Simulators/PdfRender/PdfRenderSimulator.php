<?php

declare(strict_types=1);

namespace Fundly\Integration\Simulators\PdfRender;

use Fundly\Integration\Ports\PdfRender\PdfRenderPort;
use Fundly\Integration\Ports\PdfRender\RenderedPdf;
use Fundly\Integration\Runtime\CapabilityManifest;

/**
 * Turns the HTML into text lines (block elements and table cells become line
 * and column breaks) and writes them as a real, minimal PDF in Helvetica on
 * A4 pages. Good enough to open, print and hash; layout fidelity is Gotenberg's job.
 */
final class PdfRenderSimulator implements PdfRenderPort
{
    public const KEY = 'pdf-render-simulator';

    public const VERSION = '1.0.0';

    private const LINES_PER_PAGE = 60;

    private const WRAP = 95;

    public static function capabilities(): CapabilityManifest
    {
        return new CapabilityManifest(self::KEY, self::VERSION, '1.0', 'on_prem:simulator', [
            self::OP_RENDER => ['support' => 'native', 'idempotency' => 'natural'],
        ]);
    }

    public function render(string $html): RenderedPdf
    {
        return new RenderedPdf(self::pdf(self::lines($html)), self::KEY.'/'.self::VERSION);
    }

    /** @return list<string> */
    public static function lines(string $html): array
    {
        $html = (string) preg_replace('#<(script|style)[^>]*>.*?</\1>#si', '', $html);
        $html = (string) preg_replace('#</(td|th)>#i', ' | ', $html);
        $html = (string) preg_replace('#<(br|/p|/h[1-6]|/tr|/li|/div|hr)[^>]*>#i', "\n", $html);
        $html = (string) preg_replace('#<li[^>]*>#i', '- ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $out = [];
        foreach (explode("\n", $text) as $raw) {
            $line = trim((string) preg_replace('/[ \t]+/', ' ', $raw), " |\r");
            if ($line === '' && ($out === [] || end($out) === '')) {
                continue;
            }
            foreach ($line === '' ? [''] : explode("\n", wordwrap($line, self::WRAP, "\n", true)) as $w) {
                $out[] = $w;
            }
        }

        return $out;
    }

    /** @param list<string> $lines */
    private static function pdf(array $lines): string
    {
        $pages = array_chunk($lines === [] ? [''] : $lines, self::LINES_PER_PAGE);
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($pages as $page) {
            $stream = "BT /F1 9 Tf 12 TL 50 800 Td\n";
            foreach ($page as $line) {
                $stream .= '('.self::escape($line).") Tj T*\n";
            }
            $stream .= 'ET';
            $contentId = count($objects) + 1;
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
            $pageId = $contentId + 1;
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
            $kids[] = "{$pageId} 0 R";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($out);
        $out .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }

        return $out.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private static function escape(string $s): string
    {
        $latin = (string) @iconv('UTF-8', 'Windows-1252//TRANSLIT', str_replace('₦', 'NGN ', $s));

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $latin);
    }
}
