<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Domain;

/**
 * Merges application data into a template (FR-OFR-001). Variables are always
 * HTML-escaped; blocks are HTML the platform builds itself from escaped values.
 */
final class TemplateRenderer
{
    /**
     * @param  array<string, string>  $variables
     * @param  array<string, string>  $blocks  trusted HTML built by the renderer's callers
     */
    public static function render(OfferTemplate $template, array $variables, array $blocks): string
    {
        $body = (string) preg_replace_callback('/\{\{\s*([a-z_.]+)\s*\}\}/', static function (array $m) use ($variables, $blocks): string {
            if (array_key_exists($m[1], $blocks)) {
                return $blocks[$m[1]];
            }

            return self::e($variables[$m[1]] ?? '');
        }, $template->body());

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.self::e($template->title()).'</title><style>'
            .'body{font-family:Helvetica,Arial,sans-serif;font-size:11px;color:#1b1b1b;margin:32px}'
            .'h1{font-size:18px}h2{font-size:14px;margin-top:20px}table{border-collapse:collapse;width:100%;margin:8px 0}'
            .'th,td{border:1px solid #bbb;padding:4px 6px;text-align:left}td.n,th.n{text-align:right}'
            .'.sig{margin-top:18px;border-top:1px solid #333;width:60%;padding-top:4px}'
            .'</style></head><body>'.$body.'</body></html>';
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows  label → value
     */
    public static function keyValueTable(string $heading, array $rows): string
    {
        $html = '<h2>'.self::e($heading).'</h2><table>';
        foreach ($rows as [$label, $value]) {
            $html .= '<tr><th>'.self::e($label).'</th><td>'.self::e($value).'</td></tr>';
        }

        return $html.'</table>';
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  list<int>  $numeric  column indexes aligned right
     */
    public static function table(string $heading, array $headers, array $rows, array $numeric = []): string
    {
        $cls = static fn (int $i): string => in_array($i, $numeric, true) ? ' class="n"' : '';
        $html = '<h2>'.self::e($heading).'</h2><table><tr>';
        foreach ($headers as $i => $h) {
            $html .= '<th'.$cls($i).'>'.self::e($h).'</th>';
        }
        $html .= '</tr>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $i => $cell) {
                $html .= '<td'.$cls($i).'>'.self::e($cell).'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</table>';
    }

    public static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
