<?php

use PHPUnit\Framework\TestCase;

// Covers #51: validating + storing an uploaded venue image.
final class UploadTest extends TestCase
{
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    private function tmp(string $content, string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), 'updtest') . $suffix;
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;
        return $path;
    }

    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        );
    }

    public function test_valid_png_returns_extension(): void
    {
        $path = $this->tmp($this->pngBytes(), '.png');
        $file = ['error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'tmp_name' => $path, 'name' => 'x.png'];
        $this->assertSame('png', validateUploadedImage($file));
    }

    public function test_rejects_non_image(): void
    {
        $path = $this->tmp('just text, not an image', '.txt');
        $file = ['error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'tmp_name' => $path, 'name' => 'x.txt'];
        $this->expectException(UploadException::class);
        validateUploadedImage($file);
    }

    public function test_rejects_oversize(): void
    {
        $path = $this->tmp($this->pngBytes(), '.png');
        $file = ['error' => UPLOAD_ERR_OK, 'size' => MAX_UPLOAD_BYTES + 1, 'tmp_name' => $path, 'name' => 'x.png'];
        $this->expectException(UploadException::class);
        validateUploadedImage($file);
    }

    public function test_rejects_failed_upload(): void
    {
        $file = ['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => '', 'name' => ''];
        $this->expectException(UploadException::class);
        validateUploadedImage($file);
    }

    public function test_store_writes_file_and_returns_web_path(): void
    {
        $path = $this->tmp($this->pngBytes(), '.png');
        $file = ['error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'tmp_name' => $path, 'name' => 'x.png'];

        $dest = sys_get_temp_dir() . '/padel_upload_test';
        $web = storeUploadedImage($file, $dest, 'assets/images', 'copy');

        $this->assertMatchesRegularExpression('#^assets/images/[a-f0-9]+\.png$#', $web);
        $landed = $dest . '/' . basename($web);
        $this->assertFileExists($landed);
        @unlink($landed);
        @rmdir($dest);
    }
}
