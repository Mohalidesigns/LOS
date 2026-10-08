<?php

declare(strict_types=1);

/*
 * Splices fragment files (paths, schemas, tags) into openapi.yaml between
 * generated markers, so hand-written P0 sections stay untouched and the
 * build is repeatable. Usage (from the repo root):
 *   php api/openapi/fragments/build.php
 */

require __DIR__.'/../../../backend/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$file = __DIR__.'/../openapi.yaml';
$yaml = file_get_contents($file);
$fragments = [];
foreach (glob(__DIR__.'/p*.php') ?: [] as $f) {
    $fragments[basename($f, '.php')] = require $f;
}
ksort($fragments);

$dump = static function (array $value, int $indent): string {
    $text = Yaml::dump($value, 20, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_COMPACT_NESTED_MAPPING);
    $pad = str_repeat(' ', $indent);

    return implode("\n", array_map(static fn (string $l): string => $l === '' ? '' : $pad.$l, explode("\n", rtrim($text))))."\n";
};

$splice = static function (string $yaml, string $marker, string $anchorRegex, string $block): string {
    $begin = "# >>> generated: {$marker}\n";
    $end = "# <<< generated: {$marker}\n";
    $yaml = preg_replace('/'.preg_quote($begin, '/').'.*?'.preg_quote($end, '/').'/s', '', $yaml) ?? $yaml;
    if (preg_match($anchorRegex, $yaml, $m, PREG_OFFSET_CAPTURE) !== 1) {
        fwrite(STDERR, "anchor not found for {$marker}\n");
        exit(1);
    }
    $pos = $m[0][1];

    return substr($yaml, 0, $pos).$begin.$block.$end.substr($yaml, $pos);
};

$paths = [];
$schemas = [];
$tags = [];
foreach ($fragments as $f) {
    $paths += $f['paths'];
    $schemas += $f['schemas'];
    $tags = array_merge($tags, $f['tags'] ?? []);
}

// paths: before "components:"; schemas: appended at the end of components.schemas (end of file); tags: before "paths:"
$yaml = $splice($yaml, 'paths', '/^components:$/m', $dump($paths, 2));
$yaml = $splice($yaml, 'tags', '/^paths:$/m', $dump(array_map(static fn (string $t): array => ['name' => $t], $tags), 0));
$yaml = rtrim(preg_replace('/# >>> generated: schemas\n.*?# <<< generated: schemas\n/s', '', $yaml) ?? $yaml)."\n";
$yaml .= "# >>> generated: schemas\n".$dump($schemas, 4)."# <<< generated: schemas\n";

file_put_contents($file, $yaml);
Yaml::parse($yaml); // fails loudly if the splice produced invalid YAML
echo 'openapi.yaml: '.count($paths).' generated paths, '.count($schemas)." generated schemas\n";
