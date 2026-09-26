<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ImageSanitizer;
use Tests\TestCase;

class ImageSanitizerTest extends TestCase
{
    public function test_readable_files_are_left_untouched_and_unreadable_jpegs_are_re_encoded(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'img').'.jpg';
        $image = imagecreatetruecolor(40, 20);
        imagefilledrectangle($image, 0, 0, 39, 19, imagecolorallocate($image, 200, 30, 30));
        imagejpeg($image, $path, 90);
        imagedestroy($image);
        $before = md5_file($path);

        $this->assertTrue(ImageSanitizer::readable($path));
        $this->assertFalse(ImageSanitizer::prepare($path), 'a readable file is not rewritten');
        $this->assertSame($before, md5_file($path));

        $this->assertTrue(ImageSanitizer::reencodeJpeg($path), 'the re-encoder itself rewrites a JPEG');
        [$w, $h] = getimagesize($path);
        $this->assertSame([40, 20], [$w, $h], 'same dimensions');
        $this->assertTrue(ImageSanitizer::readable($path));

        $png = tempnam(sys_get_temp_dir(), 'img').'.png';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $this->assertFalse(ImageSanitizer::reencodeJpeg($png), 'only JPEGs are re-encoded');
        $this->assertFalse(ImageSanitizer::readable('/nonexistent/file.jpg'));
        @unlink($path);
        @unlink($png);
    }
}
