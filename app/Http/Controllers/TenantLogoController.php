<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantLogoController extends Controller
{
    public function show(Tenant $tenant): StreamedResponse
    {
        abort_unless($tenant->logo_path && Storage::disk('local')->exists($tenant->logo_path), 404);

        $extension = strtolower(pathinfo($tenant->logo_path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
        abort_unless($mime !== null, 404);

        return Storage::disk('local')->response($tenant->logo_path, $tenant->slug.'-logo.'.$extension, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=120',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
