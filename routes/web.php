<?php

use App\Http\Controllers\WebDavController;
use App\Http\Controllers\WebDavFileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('webdav');
});

Route::get('/webdav/files', [WebDavFileController::class, 'list']);
Route::post('/webdav/upload', [WebDavFileController::class, 'upload']);

Route::any('/webdav/{path?}', [WebDavController::class, 'handle'])
    ->where('path', '.*')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
