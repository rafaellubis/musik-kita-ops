<?php

namespace App\Services;

/**
 * Siapkan asset PDF laporan progress (logo terkompresi) agar ukuran file kecil.
 */
class ProgressReportPdfAssetService
{
    private const LOGO_MAX_WIDTH = 160;

    private const LOGO_JPEG_QUALITY = 72;

    /**
     * Path logo JPEG kecil untuk DomPDF. Generate otomatis dari PNG sumber jika perlu.
     */
    public function optimizedLogoPath(): ?string
    {
        $source = $this->resolveSourceLogoPath();
        if ($source === null) {
            return null;
        }

        $targetDir = storage_path('app/pdf');
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $target = $targetDir . DIRECTORY_SEPARATOR . 'logo-musikkita.jpg';

        if ($this->shouldReuseCachedLogo($source, $target)) {
            return $target;
        }

        if (! function_exists('imagecreatefrompng')) {
            return $source;
        }

        $image = @imagecreatefrompng($source);
        if ($image === false) {
            return $source;
        }

        $width  = imagesx($image);
        $height = imagesy($image);
        $newWidth = min($width, self::LOGO_MAX_WIDTH);
        $newHeight = (int) round($height * ($newWidth / $width));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $white);
        imagealphablending($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        imagejpeg($resized, $target, self::LOGO_JPEG_QUALITY);

        imagedestroy($image);
        imagedestroy($resized);

        return $target;
    }

    private function resolveSourceLogoPath(): ?string
    {
        foreach ([
            public_path('images/logo-musikkita-light-mode.PNG'),
            public_path('images/logo-musikkita-dark-mode.PNG'),
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function shouldReuseCachedLogo(string $source, string $target): bool
    {
        return file_exists($target) && filemtime($target) >= filemtime($source);
    }
}
