<?php

declare(strict_types=1);

use Tests\Architecture\Support;

/*
 * FR-CBA-002: no CBA-specific identifier exists outside an adapter. The scan
 * covers source *and* comments (a vendor name in a docblock is still a leak
 * of vendor knowledge) across src/, app/, config/, routes/ and database/.
 */
it('has no core banking vendor identifiers outside src/Integration/Adapters', function () {
    $pattern = '/finacle|flexcube|t24|temenos|bankone|fineract|mifos/i';
    $offenders = [];
    foreach (Support::phpFiles('src', 'app', 'config', 'routes', 'database') as $file) {
        if (str_contains($file, '/src/Integration/Adapters/')) {
            continue;
        }
        foreach (file($file) ?: [] as $n => $line) {
            if (preg_match($pattern, $line) === 1) {
                $offenders[] = Support::relative($file).':'.($n + 1).': '.trim($line);
            }
        }
    }
    expect($offenders)->toBe([]);
})->group('FR-CBA-002', 'LOS-FR-284');

it('keeps the vendor scan honest (the pattern does catch identifiers)', function () {
    expect(preg_match('/finacle|flexcube|t24|temenos|bankone|fineract|mifos/i', 'new FinacleAdapter(); // T24 code'))->toBe(1);
})->group('FR-CBA-002');

it('reaches external systems only through ports: modules never import adapters or simulators', function () {
    $offenders = [];
    foreach (Support::phpFiles('src/Modules', 'src/Shared', 'app') as $file) {
        foreach (Support::imports($file) as $import) {
            if (str_starts_with($import, 'Fundly\\Integration\\Adapters\\') || str_starts_with($import, 'Fundly\\Integration\\Simulators\\')) {
                $offenders[] = Support::relative($file).' imports '.$import;
            }
        }
    }
    // the only allowed wiring points are service providers
    $offenders = array_values(array_filter($offenders, fn ($o) => ! str_contains($o, 'ServiceProvider.php')));
    expect($offenders)->toBe([]);
})->group('FR-CBA-020', 'FR-CBA-002');
