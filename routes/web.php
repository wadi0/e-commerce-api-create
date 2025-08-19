<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/cloudinary-check', function () {
    return response()->json([
        'cloud_name' => config('cloudinary.cloud_name'),
        'api_key' => config('cloudinary.api_key'),
        'secret_exists' => !empty(config('cloudinary.api_secret')),
        'package_loaded' => class_exists('CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary')
    ]);
});
