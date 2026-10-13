<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\PdfRender;

/**
 * HTML → PDF for offer letters and agreements (integration register I-34).
 * Bundled Gotenberg in production; a simulator that lays the text out as a
 * plain PDF in development and tests. Rendering never fetches remote assets.
 */
interface PdfRenderPort
{
    public const PORT = 'pdf_render';

    public const OP_RENDER = 'renderHtml';

    public function render(string $html): RenderedPdf;
}
