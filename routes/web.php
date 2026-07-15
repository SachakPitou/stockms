<?php

use Illuminate\Support\Facades\Route;

// Redirect root URL to admin panel
Route::get('/', function () {
    return redirect('/admin');
});