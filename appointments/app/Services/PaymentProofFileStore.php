<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentProofFileStore
{
    public const MAX_BYTES = 10485760;

    public static function store(string $contents): ?string
    {
        if ($contents === '' || strlen($contents) > self::MAX_BYTES) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($contents);
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => null,
        };

        if (!$extension) {
            return null;
        }

        $path = 'payment-proofs/' . Str::uuid() . '.' . $extension;

        return Storage::disk('public')->put($path, $contents) ? $path : null;
    }
}
