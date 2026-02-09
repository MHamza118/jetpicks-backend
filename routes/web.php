<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Serve storage files
Route::get('/storage/{path}', function ($path) {
    $fullPath = 'public/' . $path;
    
    if (!Storage::exists($fullPath)) {
        abort(404);
    }
    
    $file = Storage::get($fullPath);
    $mimeType = Storage::mimeType($fullPath);
    
    return response($file, 200, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*');
