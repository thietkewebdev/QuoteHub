<?php

namespace App\Services\Quotation\HybridExtraction;

/**
 * Renders PDF pages to temporary PNG paths (utility for rasterization workflows).
 */
final class PdfImageRenderer
{
    /**
     * @return list<string> absolute PNG paths (caller should unlink)
     */
    public function renderPagesToPng(string $absolutePdfPath): array
    {
        if (! extension_loaded('imagick')) {
            return [];
        }

        $dpi = max(72, min(400, (int) config('ocr.pdf_raster_dpi', 200)));
        $maxPages = max(1, (int) config('ocr.max_pdf_pages_ocr', 15));

        $imagick = new \Imagick;
        $imagick->setResolution((float) $dpi, (float) $dpi);
        $imagick->readImage($absolutePdfPath);

        $paths = [];
        $index = 0;

        foreach ($imagick as $page) {
            if ($index >= $maxPages) {
                break;
            }
            $index++;

            $page->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $page->setImageFormat('png');

            $tmp = tempnam(sys_get_temp_dir(), 'qh_hybrid_pdf_');
            if ($tmp === false) {
                $page->clear();
                $imagick->clear();

                return $paths;
            }

            $pngPath = $tmp.'.png';
            $page->writeImage($pngPath);
            $page->clear();
            $paths[] = $pngPath;
            @unlink($tmp);
        }

        $imagick->clear();

        return $paths;
    }

    /**
     * @param  list<string>  $pngPaths
     */
    public function cleanupPngs(array $pngPaths): void
    {
        foreach ($pngPaths as $p) {
            if (is_string($p) && $p !== '' && str_ends_with($p, '.png')) {
                @unlink($p);
            }
        }
    }
}
