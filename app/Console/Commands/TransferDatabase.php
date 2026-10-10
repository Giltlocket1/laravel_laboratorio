<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TransferDatabase extends Command
{
    protected $signature = 'labkeep:transfer {action : export o import} {file}';

    protected $description = 'Traslado portable entre SQLite y MySQL; importe únicamente sobre tablas de aplicación vacías.';

    private const TABLES = ['users', 'inventory_items', 'loans', 'change_requests', 'audit_logs', 'legacy_records'];

    public function handle(): int
    {
        $path = $this->argument('file');
        try {
            if ($this->argument('action') === 'export') {
                if (file_exists($path)) {
                    throw new RuntimeException('El destino existe; elija otro archivo.');
                }
                $data = DB::transaction(function () {
                    $tables = [];
                    foreach (self::TABLES as $table) {
                        $tables[$table] = DB::table($table)->orderBy('id')->get()->map(function ($row) use ($table) {
                            $values = (array) $row;
                            if ($table === 'users') {
                                $values['remember_token'] = null;
                            }

return $values;
                        })->all();
                    }

                    return ['format' => 'labkeep-1', 'created_at' => now()->toIso8601String(), 'tables' => $tables];
                });
                if (file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) === false) {
                    throw new RuntimeException('No se pudo escribir el archivo.');
                }
                @chmod($path, 0600);
                $this->info('Exportación creada. Contiene datos privados y hashes de contraseñas; guárdela fuera de public/. Copie también storage/app/private/attachments.');

                return self::SUCCESS;
            }
            if ($this->argument('action') !== 'import') {
                throw new RuntimeException('Acción no válida.');
            }
            if (! is_file($path)) {
                throw new RuntimeException('Archivo no encontrado.');
            }
            $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (($data['format'] ?? '') !== 'labkeep-1' || array_keys($data['tables'] ?? []) !== self::TABLES) {
                throw new RuntimeException('Formato no válido.');
            }
            foreach (self::TABLES as $table) {
                if (DB::table($table)->exists()) {
                    throw new RuntimeException('Destino no vacío: '.$table);
                }
            }
            DB::transaction(function () use ($data) {
                foreach (self::TABLES as $table) {
                    $columns = Schema::getColumnListing($table);
                    foreach ($data['tables'][$table] as $row) {
                        if (array_diff(array_keys($row), $columns)) {
                            throw new RuntimeException('Columnas desconocidas en '.$table);
                        }
                        DB::table($table)->insert($row);
                    }
                }
            });
            $this->info('Datos importados. Copie los adjuntos y configure APP_URL y la nueva APP_KEY antes de habilitar el sitio.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
