<?php

namespace App\Console\Commands;

use App\Actions\Msc\ImportDispatchedReport;
use App\Actions\Msc\ImportInternalLoadReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportInternalLoad extends Command
{
    protected $signature = 'msc:import-internal {file : Excel original de control interno} {--dispatched : Importar DESPACHADOS 2026 agrupando entregas por BL} {--execute : Guardar la carga validada; por defecto solo simula}';

    protected $description = 'Cargar el reporte interno legado y sus catálogos, con respaldo y sin duplicar BL';

    public function handle(ImportInternalLoadReport $importer): int
    {
        $path = $this->argument('file');
        if (! is_string($path) || ! is_file($path)) {
            $this->error('No se encontró el Excel.');

            return self::FAILURE;
        }
        try {
            $dispatched = (bool) $this->option('dispatched');
            $load = fn (string $file, bool $dry = true, ?string $copy = null): array => $dispatched
                ? app(ImportDispatchedReport::class)->handle($file, $dry, $copy)
                : $importer->handle($file, $dry);
            $result = $load($path);
            if ($this->option('execute')) {
                if (DB::getDriverName() !== 'sqlite' || DB::transactionLevel() !== 0) {
                    throw new \RuntimeException('Este comando requiere SQLite fuera de una transacción para respaldar la base antes de guardar.');
                }
                $folder = storage_path('app/private/imports/'.($dispatched ? 'dispatched-' : 'internal-').now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)));
                if (! mkdir($folder, 0700, true)) {
                    throw new \RuntimeException('No se pudo crear el archivo de respaldo.');
                }
                $source = $folder.'/source.xlsx';
                if (! copy($path, $source) || hash_file('sha256', $path) !== hash_file('sha256', $source)) {
                    throw new \RuntimeException('No se pudo verificar la copia del Excel.');
                }
                $pdo = DB::connection()->getPdo();
                $pdo->exec('VACUUM INTO '.$pdo->quote($folder.'/database-before.sqlite'));
                $this->line('Respaldo: '.$folder);
                $result = $load($source, false, $dispatched ? $folder.'/normalized.xlsx' : null);
                $result['source_sha256'] = hash_file('sha256', $source);
                $result['source_name'] = basename($path);
                $result['completed_at'] = now()->toIso8601String();
                $result['backup'] = $folder.'/database-before.sqlite';
                $written = file_put_contents($folder.'/result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                if ($written === false) {
                    $this->warn('La carga se guardó, pero no se pudo escribir el resumen en disco.');
                }
            }
            $this->info(($this->option('execute') ? 'Carga guardada' : 'Simulación sin guardar').': '.json_encode($result['created'], JSON_UNESCAPED_UNICODE));
            $this->line('Filas: '.$result['rows'].'; actualizados: '.$result['updated'].'; sin cambios: '.$result['unchanged'].'; observaciones: '.count($result['warnings']));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
