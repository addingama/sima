<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentFileService
{
    private const MAX_IMAGE_DIMENSION = 2000;

    private const JPEG_QUALITY = 82;

    private const WEBP_QUALITY = 82;

    private const PNG_COMPRESSION = 8;

    /**
     * @return array{disk: string, path: string, mime_type: string|null, size: int}
     */
    public function store(UploadedFile $file, string $directory): array
    {
        $directory = trim($directory, '/');

        if ($this->isCompressibleImage($file)) {
            $stored = $this->storeCompressedImage($file, $directory);

            if ($stored !== null) {
                return $stored;
            }
        }

        $path = $file->store($directory, 'local');

        abort_unless(is_string($path) && $path !== '', 500, 'Gagal menyimpan berkas ke penyimpanan lokal.');
        abort_unless(Storage::disk('local')->exists($path), 500, 'Berkas tidak ditemukan setelah diunggah ke penyimpanan lokal.');

        return [
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
        ];
    }

    private function isCompressibleImage(UploadedFile $file): bool
    {
        $extension = $this->normalizedExtension($file);

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    /**
     * @return array{disk: string, path: string, mime_type: string|null, size: int}|null
     */
    private function storeCompressedImage(UploadedFile $file, string $directory): ?array
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            return null;
        }

        $extension = $this->normalizedExtension($file);
        $target = $this->resizeIfNeeded($source);
        $binary = $this->encodeImage($target, $extension);

        imagedestroy($source);
        if ($target !== $source) {
            imagedestroy($target);
        }

        if ($binary === null) {
            return null;
        }

        $path = $directory.'/'.Str::uuid()->toString().'.'.$this->storageExtension($extension);

        Storage::disk('local')->put($path, $binary);
        abort_unless(Storage::disk('local')->exists($path), 500, 'Berkas tidak ditemukan setelah diunggah ke penyimpanan lokal.');

        return [
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $this->mimeTypeFor($extension),
            'size' => Storage::disk('local')->size($path),
        ];
    }

    private function resizeIfNeeded(\GdImage $source): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $longest = max($width, $height);

        if ($longest <= self::MAX_IMAGE_DIMENSION) {
            return $source;
        }

        $ratio = self::MAX_IMAGE_DIMENSION / $longest;
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    private function encodeImage(\GdImage $image, string $extension): ?string
    {
        ob_start();

        $success = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, null, self::JPEG_QUALITY),
            'png' => imagepng($image, null, self::PNG_COMPRESSION),
            'webp' => function_exists('imagewebp') && imagewebp($image, null, self::WEBP_QUALITY),
            default => false,
        };

        $binary = ob_get_clean();

        return $success && is_string($binary) && $binary !== '' ? $binary : null;
    }

    private function normalizedExtension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    private function storageExtension(string $extension): string
    {
        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    private function mimeTypeFor(string $extension): ?string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
    }
}
