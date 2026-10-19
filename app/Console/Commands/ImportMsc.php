<?php

namespace App\Console\Commands;

use App\Actions\Msc\ImportMscShipments;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('msc:import {file : Archivo Excel MSC} {--dry-run : Validar sin guardar registros}')]
#[Description('Importar MSC con la numeración automática del sistema')]
class ImportMsc extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ImportMscShipments $importer): int
    {
        $path = $this->argument('file');
        if (! is_string($path) || ! is_file($path)) {
            $this->error('No se encontró el archivo indicado.');

            return self::FAILURE;
        }
        try {
            $result = $importer->handle($path, (bool) $this->option('dry-run'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info(($this->option('dry-run') ? 'Validación' : 'Importación').' MSC: '.$result['imported'].' registros nuevos; '.$result['skipped'].' filas omitidas.');

        return self::SUCCESS;
    }
}
