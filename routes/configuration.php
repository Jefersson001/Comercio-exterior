<?php

use App\Http\Controllers\CategorySpreadsheetController;
use App\Http\Controllers\FactorySpreadsheetController;
use App\Http\Controllers\ProductSpreadsheetController;
use App\Http\Controllers\VesselSpreadsheetController;
use App\Livewire\Pages\Configuration\Categories\Form as CategoryForm;
use App\Livewire\Pages\Configuration\Categories\Import as CategoryImport;
use App\Livewire\Pages\Configuration\Categories\Index as CategoryIndex;
use App\Livewire\Pages\Configuration\Factories\Form as FactoryForm;
use App\Livewire\Pages\Configuration\Factories\Import as FactoryImport;
use App\Livewire\Pages\Configuration\Factories\Index as FactoryIndex;
use App\Livewire\Pages\Configuration\Products\Form as ProductForm;
use App\Livewire\Pages\Configuration\Products\Import as ProductImport;
use App\Livewire\Pages\Configuration\Products\Index as ProductIndex;
use App\Livewire\Pages\Configuration\Users\Form;
use App\Livewire\Pages\Configuration\Users\Index;
use App\Livewire\Pages\Configuration\Vessels\Form as VesselForm;
use App\Livewire\Pages\Configuration\Vessels\Import as VesselImport;
use App\Livewire\Pages\Configuration\Vessels\Index as VesselIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:manage-users'])
    ->prefix('configuracion')->name('configuration.')->group(function (): void {
        Route::get('historial', App\Livewire\Pages\History\Index::class)->name('history.index');
        Route::get('usuarios', Index::class)->name('users.index');
        Route::get('usuarios/crear', Form::class)->name('users.create');
        Route::get('usuarios/{user}/editar', Form::class)->name('users.edit');
    });

Route::middleware(['auth', 'can:manage-categories'])
    ->prefix('configuracion/categorias')->name('configuration.categories.')->group(function (): void {
        Route::get('/', CategoryIndex::class)->name('index');
        Route::get('crear', CategoryForm::class)->name('create');
        Route::get('importar', CategoryImport::class)->name('import');
        Route::get('exportar', [CategorySpreadsheetController::class, 'export'])->name('export');
        Route::get('plantilla', [CategorySpreadsheetController::class, 'template'])->name('template');
        Route::get('{category}/editar', CategoryForm::class)->name('edit');
    });

Route::middleware(['auth', 'can:manage-factories'])
    ->prefix('configuracion/fabricas')->name('configuration.factories.')->group(function (): void {
        Route::get('/', FactoryIndex::class)->name('index');
        Route::get('crear', FactoryForm::class)->name('create');
        Route::get('importar', FactoryImport::class)->name('import');
        Route::get('exportar', [FactorySpreadsheetController::class, 'export'])->name('export');
        Route::get('plantilla', [FactorySpreadsheetController::class, 'template'])->name('template');
        Route::get('{factory}/editar', FactoryForm::class)->name('edit');
    });

Route::middleware(['auth', 'can:manage-vessels'])
    ->prefix('configuracion/buques')->name('configuration.vessels.')->group(function (): void {
        Route::get('/', VesselIndex::class)->name('index');
        Route::get('crear', VesselForm::class)->name('create');
        Route::get('importar', VesselImport::class)->name('import');
        Route::get('exportar', [VesselSpreadsheetController::class, 'export'])->name('export');
        Route::get('plantilla', [VesselSpreadsheetController::class, 'template'])->name('template');
        Route::get('{vessel}/editar', VesselForm::class)->name('edit');
    });

Route::middleware(['auth', 'can:manage-products'])
    ->prefix('configuracion/productos')->name('configuration.products.')->group(function (): void {
        Route::get('/', ProductIndex::class)->name('index');
        Route::get('crear', ProductForm::class)->name('create');
        Route::get('importar', ProductImport::class)->name('import');
        Route::get('exportar', [ProductSpreadsheetController::class, 'export'])->name('export');
        Route::get('plantilla', [ProductSpreadsheetController::class, 'template'])->name('template');
        Route::get('{product}/editar', ProductForm::class)->name('edit');
    });
