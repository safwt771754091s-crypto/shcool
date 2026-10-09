<?php

namespace App\Support\Export;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders HTML to PDF (تصدير PDF) with Arabic-friendly defaults.
 *
 * dompdf has no bundled Arabic font, so a font that contains Arabic glyphs
 * must be registered for text shaping to work. The path is configurable; when
 * it is missing the export still renders (Latin) rather than crashing.
 */
class PdfExporter
{
    public function __construct(
        protected ?string $arabicFontPath = null,
        protected string $defaultFont = 'DejaVu Sans',
    ) {
        $this->arabicFontPath = $arabicFontPath
            ?? config('exports.pdf.arabic_font_path');
        $this->defaultFont = config('exports.pdf.default_font', $this->defaultFont);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function render(string $html, array $options = []): string
    {
        $dompdf = $this->make($options);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($options['paper'] ?? 'A4', $options['orientation'] ?? 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function temporary(string $html, array $options = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'export_').'.pdf';

        file_put_contents($path, $this->render($html, $options));

        return $path;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function make(array $options): Dompdf
    {
        $dompdfOptions = new Options;
        $dompdfOptions->set('isRemoteEnabled', false);
        $dompdfOptions->set('isHtml5ParserEnabled', true);
        $dompdfOptions->set('defaultFont', $this->defaultFont);
        $dompdfOptions->set('chroot', $options['chroot'] ?? base_path());

        if (filled($this->arabicFontPath) && is_file($this->arabicFontPath)) {
            $dompdfOptions->set('fontDir', dirname($this->arabicFontPath));
            $dompdfOptions->set('fontCache', dirname($this->arabicFontPath));
        }

        return new Dompdf($dompdfOptions);
    }
}
