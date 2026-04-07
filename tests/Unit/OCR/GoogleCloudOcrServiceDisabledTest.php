<?php

namespace Tests\Unit\OCR;

use App\Services\OCR\Google\GoogleCloudOcrService;
use App\Services\OCR\Google\GoogleDocumentAiOcrService;
use App\Services\OCR\Google\GoogleOcrException;
use App\Services\OCR\Google\GoogleVisionOcrService;
use Tests\TestCase;

class GoogleCloudOcrServiceDisabledTest extends TestCase
{
    public function test_throws_when_google_ocr_disabled(): void
    {
        config(['google_ocr.enabled' => false]);

        $svc = new GoogleCloudOcrService(
            new GoogleVisionOcrService,
            new GoogleDocumentAiOcrService,
        );

        $path = tempnam(sys_get_temp_dir(), 'qh_gocr_');
        $this->assertNotFalse($path);
        file_put_contents($path, 'x');

        try {
            $svc->extractFile($path, 'image/png');
            $this->fail('Expected GoogleOcrException');
        } catch (GoogleOcrException) {
            $this->assertTrue(true);
        } finally {
            @unlink($path);
        }
    }
}
