<?php

namespace App\Tests\Gazette\Send;

use App\Gazette\Send\PhotoCompressor;
use PHPUnit\Framework\TestCase;

final class PhotoCompressorTest extends TestCase
{
    private static function photo(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        // Du bruit : une image unie se compresse trop bien pour tester le poids.
        for ($i = 0; $i < 4000; ++$i) {
            imagefilledellipse($image, random_int(0, $width), random_int(0, $height), 60, 40, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        ob_start();
        imagejpeg($image, null, 95);

        return (string) ob_get_clean();
    }

    public function testResizesToMaxWidthAndStaysUnderBudget(): void
    {
        $out = (new PhotoCompressor(1200, 260_000))->compress(self::photo(1440, 1080));

        $info = getimagesizefromstring($out);
        self::assertSame([1200, 900], [$info[0], $info[1]]);
        self::assertSame('image/jpeg', $info['mime']);
        self::assertLessThanOrEqual(260_000, \strlen($out));
    }

    public function testSmallerPhotosAreNotUpscaled(): void
    {
        $out = (new PhotoCompressor(1200, 1_000_000))->compress(self::photo(800, 600));

        $info = getimagesizefromstring($out);
        self::assertSame([800, 600], [$info[0], $info[1]]);
    }

    public function testQualityDropsUntilItFits(): void
    {
        $big = self::photo(1200, 1200);
        $loose = (new PhotoCompressor(1200, 10_000_000))->compress($big);
        $tight = (new PhotoCompressor(1200, 60_000))->compress($big);

        self::assertLessThan(\strlen($loose), \strlen($tight));
    }

    public function testRejectsGarbage(): void
    {
        $this->expectExceptionMessage('Image illisible');
        (new PhotoCompressor())->compress('pas une image');
    }
}
