<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\LegacyRecord;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class ImportLegacy extends Command
{
    protected $signature = 'labkeep:import-legacy {directory} {--apply : Aplicar; por defecto sólo inspecciona} {--uploads= : Carpeta static/uploads original}';

    protected $description = 'Importar copias SQLite originales sin modificar los archivos fuente.';

    public function handle(): int
    {
        $root = realpath($this->argument('directory'));
        if (! $root) {
            $this->error('Directorio de origen no encontrado.');

            return self::FAILURE;
        }
        $sources = [];
        foreach (array_keys(config('labkeep.inventories')) as $inventory) {
            $path = $root.DIRECTORY_SEPARATOR.$inventory.'.db';
            if (! is_file($path)) {
                continue;
            }
            $source = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $source->exec('PRAGMA query_only = ON');
            $sources[$inventory] = $source;
        }
        if (! $sources) {
            $this->error('No se encontraron inventario.db, oficina.db o sistemas.db.');

            return self::FAILURE;
        }
        foreach ($sources as $inventory => $source) {
            foreach ($this->tables($source) as $table) {
                $count = $source->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn();
                $this->line($inventory.' / '.$table.': '.$count);
            }
        }
        if (! $this->option('apply')) {
            $this->info('Inspección terminada. Use --apply sobre una base destino vacía para importar.');

            return self::SUCCESS;
        }
        foreach (array_keys($sources) as $inventory) {
            if (InventoryItem::withTrashed()->where('inventory', $inventory)->exists() || LegacyRecord::where('inventory', $inventory)->exists() || Loan::where('inventory', $inventory)->exists()) {
                $this->error('El inventario '.$inventory.' ya contiene datos. Importación cancelada para evitar sobrescrituras.');

                return self::FAILURE;
            }
        }
        try {
            DB::transaction(function () use ($sources) {
                foreach ($sources as $inventory => $source) {
                    $tables = $this->tables($source);
                    foreach ($tables as $table) {
                        foreach ($source->query('SELECT * FROM "'.$table.'"', PDO::FETCH_ASSOC) as $row) {
                            $archived = $row;
                            if ($table === 'users') {
                                unset($archived['password']);
                                $archived['password_reset_required'] = true;
                            }
                            LegacyRecord::create(['inventory' => $inventory, 'source_table' => $table, 'source_id' => (string) ($row['id'] ?? hash('sha256', json_encode($row, JSON_THROW_ON_ERROR))), 'data' => $archived]);
                            if (array_key_exists($table, config('labkeep.categories'))) {
                                $details = $row;
                                if (filter_var($row['pdf_path'] ?? '', FILTER_VALIDATE_URL) && preg_match('~^https?://~', $row['pdf_path'])) {
                                    $details['pdf_external_url'] = $row['pdf_path'];
                                }
                                foreach (['image_path', 'pdf_path'] as $field) {
                                    $details[$field] = $this->copyAttachment($row[$field] ?? null, $inventory);
                                }
                                $date = $row['expiration_date'] ?? null;
                                if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                                    $date = null;
                                }
                                $quantity = $table === 'substances' ? ($row['stock_units'] ?? 1) : ($row['quantity'] ?? 1);
                                if (! is_numeric($quantity) || $quantity < 0) {
                                    throw new RuntimeException('Existencia no válida en '.$inventory.'/'.$table.'/'.$row['id']);
                                }
                                InventoryItem::create([
                                    'inventory' => $inventory, 'category' => $table, 'legacy_id' => $row['id'],
                                    'name' => $row['name'] ?? $row['nombre'] ?? 'Sin nombre',
                                    'quantity' => $quantity, 'unit' => $table === 'substances' ? 'envases' : ($row['unit'] ?? 'piezas'),
                                    'location' => $row['location'] ?? null, 'expiration_date' => $date,
                                    'qr_content' => $row['qr_content'] ?? 'LAB-'.strtoupper($table).'-'.$row['id'], 'details' => $details,
                                ]);
                            }
                            if ($table === 'users' && $inventory === 'inventario') {
                                $this->importUser($row);
                            }
                        }
                    }
                }
                foreach ($sources as $inventory => $source) {
                    if (in_array('loans', $this->tables($source), true)) {
                        foreach ($source->query('SELECT * FROM loans', PDO::FETCH_ASSOC) as $row) {
                            $this->importLoan($row, $inventory);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            report($e);
            $this->error('Importación revertida: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('Importación completada. Las cuentas antiguas necesitan contraseña nueva y activación. Las solicitudes antiguas se conservan en Archivo histórico.');

        return self::SUCCESS;
    }

    private function tables(PDO $source): array
    {
        $tables = $source->query("SELECT name FROM sqlite_master WHERE type='table' AND name <> 'sqlite_sequence'")->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter($tables, fn ($t) => preg_match('/^[a-z_]+$/', $t)));
    }

    private function copyAttachment(?string $path, string $inventory): ?string
    {
        if (! $path || ! $this->option('uploads')) {
            return null;
        }
        $relative = preg_replace('~^/?(?:static/)?uploads/~', '', $path);
        if (str_contains($relative, '..') || str_contains($relative, ':') || str_starts_with($relative, '/')) {
            return null;
        }
        $root = realpath($this->option('uploads'));
        $source = $root ? realpath($root.DIRECTORY_SEPARATOR.$relative) : false;
        if (! $source || ! str_starts_with($source, $root.DIRECTORY_SEPARATOR) || ! is_file($source)) {
            $this->warn('Adjunto ausente: '.$relative);

            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
            return null;
        }
        $relative = str_replace('\\', '/', $relative);
        Storage::disk('local')->put('attachments/'.$inventory.'/legacy/'.$relative, file_get_contents($source));

        return 'files/'.$inventory.'/legacy/'.$relative;
    }

    private function importUser(array $row): void
    {
        $username = $this->decodeUsername($row['username']);
        if (User::where('username', $username)->exists()) {
            return;
        }
        $email = $row['email'] ?? '';
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists()) {
            $email = 'legacy-'.$row['id'].'@migration.invalid';
        }
        $allowed = array_values(array_intersect(explode(',', $row['allowed_inventories'] ?? 'inventario'), array_keys(config('labkeep.inventories'))));
        User::create(['username' => $username, 'name' => $username, 'email' => $email, 'password' => Str::password(48), 'active' => false, 'must_change_password' => true, 'role' => in_array($row['role'] ?? '', ['admin', 'jefe', 'responsable']) ? $row['role'] : 'responsable', 'allowed_inventories' => $allowed]);
    }

    private function decodeUsername(string $value): string
    {
        $bytes = base64_decode($value, true);
        if ($bytes === false) {
            return $value;
        }
        $key = hash('sha256', 'itma2-super-secret-key-laboratorio', true);
        $decoded = '';
        for ($i = 0; $i < strlen($bytes); $i++) {
            $decoded .= $bytes[$i] ^ $key[$i % strlen($key)];
        }

        return mb_check_encoding($decoded, 'UTF-8') && ! preg_match('/[\x00-\x1f]/', $decoded) ? $decoded : $value;
    }

    private function importLoan(array $row, string $inventory): void
    {
        $raw = json_decode($row['items_json'] ?? '[]', true) ?: [];
        $lines = [];
        foreach ($raw as $line) {
            $category = match ($line['type'] ?? '') {
                'substance' => 'substances','material','chemical_material' => 'chemical_materials','didactic_material' => 'didactic_materials','equipo' => 'equipos',default => null
            };
            $item = $category ? InventoryItem::where('inventory', $inventory)->where('category', $category)->where('legacy_id', $line['id'])->first() : null;
            if (! $item) {
                throw new RuntimeException('Préstamo '.$row['id'].' con artículo sin correspondencia; revisar antes de migrar.');
            }
            $quantity = (float) ($line['quantity'] ?? 1);
            if ($quantity <= 0) {
                throw new RuntimeException('Cantidad de préstamo no válida.');
            }
            $lines[] = ['id' => $item->id, 'name' => $item->name, 'quantity' => $quantity, 'unit' => $item->unit];
        }
        if (! $lines) {
            $this->warn('Préstamo '.$row['id'].' conservado únicamente en archivo: sin partidas.');

            return;
        }
        $status = match ($row['status']) {
            'Prestado' => 'approved','Devuelto' => 'returned','Pendiente Aprobación Admin' => 'pending','Pendiente Verificación','Pendiente Devolución' => 'return_pending','Rechazado' => 'rejected',default => null
        };
        if (! $status) {
            throw new RuntimeException('Estado de préstamo sin mapear: '.$row['status']);
        }
        if (in_array($status, ['approved', 'return_pending'])) {
            foreach ($lines as $line) {
                $item = InventoryItem::findOrFail($line['id']);
                if ($line['quantity'] > (float) $item->quantity) {
                    throw new RuntimeException('Préstamo activo excede existencias: '.$item->name);
                }
                $item->decrement('quantity', $line['quantity']);
            }
        }
        $user = User::where('username', $row['borrower_name'])->first();
        Loan::create(['inventory' => $inventory, 'user_id' => $user?->id, 'borrower_name' => $row['borrower_name'], 'items' => $lines, 'status' => $status, 'notes' => $row['notes'] ?? null, 'approved_at' => $status !== 'pending' ? ($row['loan_date'] ?? null) : null, 'returned_at' => $status === 'returned' ? ($row['return_date'] ?? null) : null, 'feedback' => 'Importado del préstamo original #'.$row['id']]);
    }
}
