<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SupabaseStorageService
{
    protected string $supabaseUrl;

    protected string $serviceKey;

    protected string $bucket;

    public function __construct()
    {
        $this->supabaseUrl = rtrim(env('SUPABASE_URL', ''), '/');
        $this->serviceKey = env('SUPABASE_SERVICE_KEY', '');
        $this->bucket = env('SUPABASE_BUCKET', 'lost-pets');
    }

    /**
     * Re-encode and upload an image using a server-generated name.
     * Re-encoding strips EXIF, GPS, comments and other embedded metadata.
     */
    public function uploadPhoto(
        UploadedFile $file,
        string $ownerId,
        string $category = 'reports'
    ): string {
        if (! Str::isUuid($ownerId)) {
            throw new InvalidArgumentException('Invalid photo owner identifier.');
        }

        if (! in_array($category, ['reports', 'sightings'], true)) {
            throw new InvalidArgumentException('Invalid photo category.');
        }

        [$contents, $mimeType, $extension] = $this->sanitizeImage($file);

        $directory = "reports/{$ownerId}";
        if ($category === 'sightings') {
            $directory .= '/sightings';
        }

        $fileName = $directory.'/'.Str::uuid().'.'.$extension;
        $uploadUrl = "{$this->supabaseUrl}/storage/v1/object/{$this->bucket}/{$fileName}";

        $response = Http::withToken($this->serviceKey)
            ->timeout(15)
            ->retry(2, 200)
            ->withHeaders(['Content-Type' => $mimeType])
            ->withBody($contents, $mimeType)
            ->post($uploadUrl);

        if (! $response->successful()) {
            Log::warning('Supabase photo upload failed.', [
                'status' => $response->status(),
                'category' => $category,
            ]);

            throw new RuntimeException('No se pudo almacenar la fotografía.');
        }

        return "{$this->supabaseUrl}/storage/v1/object/public/{$this->bucket}/{$fileName}";
    }

    /**
     * Decode and rebuild an image to reject polyglots and remove metadata.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function sanitizeImage(UploadedFile $file): array
    {
        $raw = $file->getContent();
        $imageInfo = @getimagesizefromstring($raw);

        if ($imageInfo === false || empty($imageInfo['mime'])) {
            throw new InvalidArgumentException('El archivo no es una imagen válida.');
        }

        $formats = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (! isset($formats[$imageInfo['mime']])) {
            throw new InvalidArgumentException('Formato de imagen no permitido.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || ($width * $height) > 36_000_000) {
            throw new InvalidArgumentException('Las dimensiones de la imagen no son válidas.');
        }

        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            throw new InvalidArgumentException('No se pudo procesar la imagen.');
        }

        ob_start();

        try {
            $encoded = match ($imageInfo['mime']) {
                'image/jpeg' => imagejpeg($image, null, 85),
                'image/png' => $this->encodePng($image),
                'image/webp' => imagewebp($image, null, 85),
            };
            $sanitized = ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        if (! $encoded || ! is_string($sanitized) || $sanitized === '') {
            throw new RuntimeException('No se pudo sanear la fotografía.');
        }

        return [$sanitized, $imageInfo['mime'], $formats[$imageInfo['mime']]];
    }

    private function encodePng(\GdImage $image): bool
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return imagepng($image, null, 6);
    }
}
