<?php

use App\Livewire\Pages\Home\Dashboard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home.welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');
});

require __DIR__.'/configuration.php';
require __DIR__.'/msc.php';
require __DIR__.'/settings.php';
