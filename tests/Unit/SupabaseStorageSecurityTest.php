<?php

namespace Tests\Unit;

use App\Services\SupabaseStorageService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupabaseStorageSecurityTest extends TestCase
{
    public function test_uploaded_images_are_reencoded_without_trailing_metadata(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        $marker = 'GPS_METADATA_MUST_NOT_SURVIVE';
        $path = tempnam(sys_get_temp_dir(), 'petsworld-photo-');
        file_put_contents($path, $jpeg.$marker);

        $file = new UploadedFile($path, 'unsafe.jpg', 'image/jpeg', null, true);
        (new SupabaseStorageService)->uploadPhoto($file, Str::uuid()->toString());

        Http::assertSent(function (Request $request) use ($marker): bool {
            return $request->header('Content-Type')[0] === 'image/jpeg'
                && ! str_contains($request->body(), $marker)
                && str_ends_with($request->url(), '.jpg');
        });
    }
}
