<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Cloudinary\Cloudinary;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Cloudinary manual configuration with null check
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        if ($cloudName && $apiKey && $apiSecret) {
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ],
            ]);
            $this->app->instance(Cloudinary::class, $cloudinary);
        }
    }
}