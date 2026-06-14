<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SupabaseStorageService
{
    /**
     * The Supabase project URL.
     */
    protected string $supabaseUrl;

    /**
     * The Supabase service role key.
     */
    protected string $serviceKey;

    /**
     * The storage bucket name.
     */
    protected string $bucket;

    public function __construct()
    {
        $this->supabaseUrl = rtrim(env('SUPABASE_URL', ''), '/');
        $this->serviceKey = env('SUPABASE_SERVICE_KEY', '');
        $this->bucket = env('SUPABASE_BUCKET', 'lost-pets');
    }

    /**
     * Upload a photo to Supabase Storage.
     *
     * @param  UploadedFile  $file      The uploaded file instance
     * @param  string        $reportId  The report UUID to organize the file
     * @return string The public URL of the uploaded file
     *
     * @throws RuntimeException If the upload fails
     */
    public function uploadPhoto(UploadedFile $file, string $reportId): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = "reports/{$reportId}/" . Str::uuid() . ".{$extension}";

        $uploadUrl = "{$this->supabaseUrl}/storage/v1/object/{$this->bucket}/{$fileName}";

        $response = Http::withToken($this->serviceKey)
            ->withHeaders([
                'Content-Type' => $file->getMimeType(),
            ])
            ->withBody($file->getContent(), $file->getMimeType())
            ->post($uploadUrl);

        if (!$response->successful()) {
            throw new RuntimeException(
                "Error al subir archivo a Supabase Storage: {$response->status()} - {$response->body()}"
            );
        }

        // Return the public URL
        return "{$this->supabaseUrl}/storage/v1/object/public/{$this->bucket}/{$fileName}";
    }
}
