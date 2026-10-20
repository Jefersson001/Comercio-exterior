<?php

use App\Http\Controllers\MscSpreadsheetController;
use App\Livewire\Pages\Msc\Form;
use App\Livewire\Pages\Msc\Import;
use App\Livewire\Pages\Msc\Index;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-international-trade'])->prefix('msc')->name('msc.')->group(function (): void {
    Route::get('/', Index::class)->name('index');
    Route::get('crear', Form::class)->name('create');
    Route::get('importar', Import::class)->name('import');
    Route::get('exportar', [MscSpreadsheetController::class, 'export'])->name('export');
    Route::get('reporte-control-carga-directo', [MscSpreadsheetController::class, 'exportDirect'])->name('export-direct');
    Route::get('plantilla', [MscSpreadsheetController::class, 'template'])->name('template');
    Route::get('{shipment}/editar', Form::class)->name('edit');
});

Route::middleware(['auth', 'can:access-foreign-trade'])->prefix('comercio-exterior/msc')->name('foreign-trade.msc.')->group(function (): void {
    Route::get('/', Index::class)->defaults('foreignTrade', true)->name('index');
    Route::get('crear', Form::class)->defaults('foreignTrade', true)->name('create');
    Route::get('importar', Import::class)->defaults('foreignTrade', true)->name('import');
    Route::get('exportar', [MscSpreadsheetController::class, 'export'])->name('export');
    Route::get('reporte-control-carga-directo', [MscSpreadsheetController::class, 'exportDirect'])->name('export-direct');
    Route::get('plantilla', [MscSpreadsheetController::class, 'template'])->name('template');
    Route::get('{shipment}/editar', Form::class)->defaults('foreignTrade', true)->name('edit');
});
