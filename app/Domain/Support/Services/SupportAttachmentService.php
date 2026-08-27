<?php

namespace App\Domain\Support\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SupportAttachmentService
{
    /** Stores support attachments on the private local disk; paths are never public URLs. */
    public function store(array $files): array
    {
        return collect($files)->map(function (UploadedFile $file): array {
            if (! $file->isValid() || $file->getSize() > 5 * 1024 * 1024 || ! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
                throw new InvalidArgumentException('فایل پیوست نامعتبر است.');
            }
            $extension = strtolower($file->extension() ?: 'bin');
            $path = 'support/'.now()->format('Ym').'/'.Str::uuid().'.'.$extension;
            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

            return ['path' => $path, 'name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType(), 'size' => $file->getSize()];
        })->all();
    }
}
