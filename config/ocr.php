<?php

return [

    'tesseract_binary' => env('TESSERACT_BINARY', 'tesseract'),

    /*
    |--------------------------------------------------------------------------
    | Tesseract language(s), e.g. eng, eng+deu
    |--------------------------------------------------------------------------
    */
    'tesseract_lang' => env('TESSERACT_LANG', 'eng'),

    /*
    |--------------------------------------------------------------------------
    | Per-run timeout for Tesseract (seconds). 0 = library default.
    |--------------------------------------------------------------------------
    */
    'tesseract_timeout' => (int) env('TESSERACT_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Max PDF pages to rasterize for OCR (Imagick + Tesseract fallback).
    |--------------------------------------------------------------------------
    */
    'max_pdf_pages_ocr' => (int) env('OCR_MAX_PDF_PAGES', 15),

    /*
    |--------------------------------------------------------------------------
    | Raster DPI when converting PDF pages to images for Tesseract.
    |--------------------------------------------------------------------------
    */
    'pdf_raster_dpi' => (int) env('OCR_PDF_RASTER_DPI', 200),

];
