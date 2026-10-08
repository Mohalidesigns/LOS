<?php

declare(strict_types=1);

namespace Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class Support
{
    /** @return list<string> absolute paths of PHP files under the given directories */
    public static function phpFiles(string ...$dirs): array
    {
        $out = [];
        foreach ($dirs as $dir) {
            $abs = base_path($dir);
            if (! is_dir($abs)) {
                continue;
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs)) as $f) {
                if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                    $out[] = $f->getPathname();
                }
            }
        }
        sort($out);

        return $out;
    }

    /** Source with comments and docblocks removed (so prose cannot trip code rules). */
    public static function code(string $file): string
    {
        $out = '';
        foreach (token_get_all((string) file_get_contents($file)) as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($t) ? $t[1] : $t;
        }

        return $out;
    }

    /** @return list<string> imported class names (use statements) */
    public static function imports(string $file): array
    {
        preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+\w+)?;/m', (string) file_get_contents($file), $m);

        return $m[1];
    }

    public static function relative(string $file): string
    {
        return str_replace(base_path().'/', '', $file);
    }
}
