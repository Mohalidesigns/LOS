<?php

declare(strict_types=1);

use Fundly\Integration\Simulators\PdfRender\PdfRenderSimulator;
use Fundly\Modules\Offer\Domain\Amounts;
use Fundly\Modules\Offer\Domain\KeyFacts;
use Fundly\Modules\Offer\Domain\OfferTemplate;
use Fundly\Modules\Offer\Domain\SigningOrder;
use Fundly\Modules\Offer\Domain\TemplateRenderer;
use Tests\Support\LendingFixtures;

it('recovers the nominal rate as APR when there are no fees, and raises it when fees come off the top', function () {
    // 1,000 at 1% a month over 12 months: instalment 88.8488
    $flows = array_fill(0, 12, ['total' => '88.8488', 'interest' => '0']);
    expect(KeyFacts::compute('1000', 2, $flows, [])['apr_percent'])->toBe('12.00');
    $withFee = KeyFacts::compute('1000', 2, $flows, [['code' => 'MGMT', 'name' => 'Management', 'type' => 'upfront', 'calc' => 'percent', 'value' => '2']]);
    expect($withFee)->toMatchArray(['total_fees' => '20.00', 'net_disbursed' => '980.00'])
        ->and((float) $withFee['apr_percent'])->toBeGreaterThan(15.0)->toBeLessThan(16.5)
        ->and((float) $withFee['effective_annual_rate_percent'])->toBeGreaterThan((float) $withFee['apr_percent']);
})->group('FR-CMP-025', 'FR-OFR-002');

it('lists contingent fees without costing them, and flat fees at face value', function () {
    $f = KeyFacts::compute('500000', 2, [['total' => '510000', 'interest' => '10000']], [
        ['code' => 'LEGAL', 'name' => 'Legal', 'type' => 'amortised', 'calc' => 'flat', 'value' => '2500'],
        ['code' => 'PEN', 'name' => 'Late payment', 'type' => 'contingent', 'calc' => 'percent', 'value' => '2'],
    ]);
    expect($f)->toMatchArray(['total_fees' => '2500.00', 'total_cost_of_credit' => '12500.00', 'net_disbursed' => '500000.00'])
        ->and(array_column($f['fees'], 'costed'))->toBe([true, false]);
})->group('FR-CMP-025');

it('formats money with grouping and exact decimals', function () {
    expect(Amounts::money('12500000', 'NGN'))->toBe('NGN 12,500,000.00')
        ->and(Amounts::money('999.995', 'NGN'))->toBe('NGN 1,000.00')
        ->and(Amounts::percent('24.5'))->toBe('24.50%');
});

it('escapes merged values and keeps platform blocks intact', function () {
    $t = OfferTemplate::of(LendingFixtures::offerTemplate(['body_html' => '<p>{{ applicant.name }}</p>{{ key_facts }}{{ schedule }}{{ signatures }}']));
    $html = TemplateRenderer::render($t, ['applicant.name' => '<b>Ade & Co</b>'], ['key_facts' => '<table>KF</table>', 'schedule' => '', 'signatures' => '']);
    expect($html)->toContain('&lt;b&gt;Ade &amp; Co&lt;/b&gt;')->toContain('<table>KF</table>')->not->toContain('<b>Ade');
})->group('FR-OFR-001');

it('orders signatories by template role order and finds the next to sign', function () {
    $parties = [['party_id' => 'g1', 'role' => 'guarantor', 'name' => 'G'], ['party_id' => 'p1', 'role' => 'primary', 'name' => 'P'], ['party_id' => 'j1', 'role' => 'joint', 'name' => 'J']];
    $order = SigningOrder::expected(['primary', 'guarantor', 'bank'], $parties, 'Demo Bank');
    expect(array_map(fn ($s) => [$s['position'], $s['role'], $s['party_id']], $order))->toBe([[1, 'primary', 'p1'], [2, 'guarantor', 'g1'], [3, 'bank', null]])
        ->and(SigningOrder::nextToSign([['id' => 'b', 'position' => 2, 'signed' => false], ['id' => 'a', 'position' => 1, 'signed' => true]]))->toBe('b')
        ->and(SigningOrder::nextToSign([['id' => 'a', 'position' => 1, 'signed' => true]]))->toBeNull();
})->group('FR-OFR-009');

it('renders a real PDF from HTML in the simulator', function () {
    $pdf = (new PdfRenderSimulator)->render('<h1>Offer</h1><table><tr><td>Amount</td><td>₦1,000</td></tr></table><script>x</script>');
    expect($pdf->bytes())->toStartWith('%PDF-1.4')->toEndWith("%%EOF\n")->toContain('(Amount | NGN 1,000) Tj')->not->toContain('script')
        ->and($pdf->sha256)->toBe(hash('sha256', $pdf->bytes()));
});
