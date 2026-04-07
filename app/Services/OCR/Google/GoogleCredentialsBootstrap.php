<?php

namespace App\Services\OCR\Google;

/**
 * Points Google client libraries at a service-account JSON key via GOOGLE_APPLICATION_CREDENTIALS.
 */
final class GoogleCredentialsBootstrap
{
    public static function apply(): void
    {
        $path = self::resolveCredentialsPath();
        if ($path === null) {
            return;
        }

        putenv('GOOGLE_APPLICATION_CREDENTIALS='.$path);
        $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] = $path;
    }

    public static function resolveCredentialsPath(): ?string
    {
        $raw = config('google_ocr.credentials_path');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);
        if (is_file($raw) && is_readable($raw)) {
            return $raw;
        }

        $base = base_path($raw);
        if (is_file($base) && is_readable($base)) {
            return $base;
        }

        return null;
    }

    /**
     * @throws GoogleOcrException
     */
    public static function requirePath(): string
    {
        $path = self::resolveCredentialsPath();
        if ($path === null) {
            throw new GoogleOcrException(
                __('Set GOOGLE_APPLICATION_CREDENTIALS or google_ocr.credentials_path to a readable service-account JSON file.')
            );
        }

        return $path;
    }
}
