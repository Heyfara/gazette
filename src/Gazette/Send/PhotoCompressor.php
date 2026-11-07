<?php

namespace App\Gazette\Send;

/**
 * Recompresse une photo pour le mail : JPEG, au plus `maxWidth` px de large,
 * qualité baissée par paliers jusqu'à tenir sous `maxBytes`. La preview
 * Immich (1440 px, ~400 Ko) devient ~150 à 250 Ko.
 */
final readonly class PhotoCompressor
{
    public function __construct(
        private int $maxWidth = 1200,
        private int $maxBytes = 260_000,
    ) {
    }

    public function compress(string $bytes): string
    {
        $image = @imagecreatefromstring($bytes);
        if (false === $image) {
            throw new \RuntimeException('Image illisible : ni JPEG, ni PNG, ni WebP.');
        }
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width > $this->maxWidth) {
            $resized = imagescale($image, $this->maxWidth, (int) round($height * $this->maxWidth / $width), \IMG_BICUBIC);
            if (false === $resized) {
                throw new \RuntimeException('Redimensionnement impossible.');
            }
            $image = $resized;
        }
        imageinterlace($image, true);

        $out = '';
        foreach ([85, 78, 70, 62, 55] as $quality) {
            ob_start();
            imagejpeg($image, null, $quality);
            $out = (string) ob_get_clean();
            if (\strlen($out) <= $this->maxBytes) {
                break;
            }
        }

        return $out;
    }
}
