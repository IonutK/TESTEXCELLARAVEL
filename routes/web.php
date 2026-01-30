<?php

use App\Http\Controllers\WebDavController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('webdav');
});

Route::any('/webdav/{path?}', [WebDavController::class, 'handle'])
    ->where('path', '.*')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
