<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LocalMediaService
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Stores an approved local image, records checksum/geometry and optionally creates a WebP variant. */
    public function storeImage(UploadedFile $file, string $directory = 'products'): MediaAsset
    {
        if (! $file->isValid() || ! in_array($file->getMimeType(), self::IMAGE_MIMES, true) || $file->getSize() > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('فایل تصویر نامعتبر است یا از محدودیت ۸ مگابایت عبور کرده است.');
        }

        $bytes = file_get_contents($file->getRealPath());
        $checksum = hash('sha256', $bytes);
        $extension = strtolower($file->extension() ?: 'jpg');
        $name = Str::uuid().'.'.$extension;
        $path = trim($directory, '/').'/'.$name;
        Storage::disk('public')->put($path, $bytes);

        [$width, $height] = getimagesize($file->getRealPath()) ?: [null, null];
        $variantPath = $this->createWebpVariant($bytes, $directory);

        return MediaAsset::query()->create([
            'uploaded_by' => auth()->id(),
            'disk' => 'public',
            'path' => $path,
            'variant_path' => $variantPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'checksum' => $checksum,
        ]);
    }

    /** Deletes both original and generated local variants before removing metadata. */
    public function delete(MediaAsset $asset): void
    {
        Storage::disk($asset->disk)->delete(array_filter([$asset->path, $asset->variant_path]));
        $asset->delete();
    }

    /** Creates a WebP variant when GD/WebP is available and safely falls back otherwise. */
    private function createWebpVariant(string $bytes, string $directory): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            return null;
        }
        ob_start();
        imagewebp($image, null, 82);
        $encoded = ob_get_clean();
        imagedestroy($image);
        if (! $encoded) {
            return null;
        }
        $path = trim($directory, '/').'/variants/'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, $encoded);

        return $path;
    }
}
