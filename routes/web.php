<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home.welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'pages.home.index')->name('dashboard');
});

require __DIR__.'/configuration.php';
require __DIR__.'/msc.php';
require __DIR__.'/settings.php';
