<?php

declare(strict_types=1);

use Tests\Architecture\Support;

it('keeps domain layers free of the framework', function () {
    $offenders = [];
    $dirs = array_merge(glob(base_path('src/Modules/*/Domain')) ?: [], array_map('base_path', ['src/Shared/Money', 'src/Shared/Id', 'src/Shared/Json', 'src/Shared/Pii']));
    foreach ($dirs as $dir) {
        foreach (Support::phpFiles(Support::relative($dir)) as $file) {
            foreach (Support::imports($file) as $import) {
                if (str_starts_with($import, 'Illuminate\\') || str_starts_with($import, 'Laravel\\') || str_contains($import, '\\Infrastructure\\')) {
                    $offenders[] = Support::relative($file).' imports '.$import;
                }
            }
            if (preg_match('/\\\\?Illuminate\\\\|\bDB::|\bapp\(|\bconfig\(|\bnow\(/', Support::code($file)) === 1) {
                $offenders[] = Support::relative($file).' references the framework';
            }
        }
    }
    expect($offenders)->toBe([]);
});

it('keeps controllers thin: no Infrastructure, no DB facade, no handlers, no direct writes', function () {
    $offenders = [];
    foreach (Support::phpFiles('src') as $file) {
        if (! str_ends_with($file, 'Controller.php') || ! str_contains($file, '/Http/')) {
            continue;
        }
        foreach (Support::imports($file) as $import) {
            if (str_contains($import, '\\Infrastructure\\') || str_contains($import, '\\Models\\') || $import === 'Illuminate\\Support\\Facades\\DB' || str_ends_with($import, 'Handler') || str_contains($import, 'Eloquent')) {
                $offenders[] = Support::relative($file).' imports '.$import;
            }
        }
        if (preg_match('/->(save|forceFill|update|delete|insert|create|forceDelete|increment|decrement)\(|::(create|insert|update|destroy)\(|DB::/', Support::code($file)) === 1) {
            $offenders[] = Support::relative($file).' performs a direct write';
        }
    }
    expect($offenders)->toBe([]);
});

it('routes every state change through the command bus: commands have handlers, handlers never manage transactions', function () {
    $offenders = [];
    foreach (Support::phpFiles('src') as $file) {
        $code = Support::code($file);
        if (preg_match('/implements\s+[^{]*\bCommand\b/', $code) === 1 && ! str_contains($code, '#[HandledBy(')) {
            $offenders[] = Support::relative($file).' is a Command without #[HandledBy]';
        }
        if (preg_match('/implements\s+[^{]*\bCommandHandler\b/', $code) === 1) {
            if (! str_contains($file, '/Application/')) {
                $offenders[] = Support::relative($file).' handler outside an Application layer';
            }
            if (preg_match('/->transaction\(|DB::transaction|beginTransaction|->commit\(/', $code) === 1) {
                $offenders[] = Support::relative($file).' handler manages its own transaction';
            }
        }
    }
    expect($offenders)->toBe([]);
    // only the bus may invoke handlers
    foreach (Support::phpFiles('src', 'app', 'routes') as $file) {
        if (str_ends_with($file, 'CommandBus.php') || str_contains($file, '/Application/')) {
            continue;
        }
        expect(preg_match('/Handler\)->handle\(|Handler::class\)->handle\(/', Support::code($file)))->toBe(0, Support::relative($file));
    }
});

it('crosses module boundaries only through Contracts', function () {
    $offenders = [];
    foreach (glob(base_path('src/Modules/*'), GLOB_ONLYDIR) ?: [] as $moduleDir) {
        $module = basename($moduleDir);
        foreach (Support::phpFiles('src/Modules/'.$module) as $file) {
            foreach (Support::imports($file) as $import) {
                if (preg_match('/^Fundly\\\\Modules\\\\(\w+)\\\\(\w+)/', $import, $m) === 1 && $m[1] !== $module && $m[2] !== 'Contracts') {
                    // Service providers wire the shared maker-checker registry; that is the one sanctioned exception.
                    if (str_ends_with($file, 'ServiceProvider.php') && str_ends_with($import, 'ChangeActionRegistry')) {
                        continue;
                    }
                    $offenders[] = Support::relative($file).' imports '.$import;
                }
            }
        }
    }
    expect($offenders)->toBe([]);
});

it('declares strict types in every PHP file', function () {
    $missing = [];
    foreach (Support::phpFiles('src', 'app', 'config', 'routes', 'database', 'tests', 'bootstrap') as $file) {
        if (str_contains($file, '/bootstrap/cache/')) {
            continue;
        }
        if (! str_contains((string) file_get_contents($file), 'declare(strict_types=1);')) {
            $missing[] = Support::relative($file);
        }
    }
    expect($missing)->toBe([]);
});

it('never uses floats in application code (money and rates are decimals)', function () {
    $offenders = [];
    foreach (Support::phpFiles('src', 'app') as $file) {
        $code = Support::code($file);
        if (preg_match('/\bfloat\b|\(double\)|\bfloatval\(|\bfloor\(|\bceil\(|\bround\(|\bfdiv\(|\bmicrotime\(true\)/i', $code, $m) === 1) {
            $offenders[] = Support::relative($file).': '.$m[0];
        }
    }
    expect($offenders)->toBe([]);
});
