<?php

namespace Tests\Feature;

use App\Models\ChangeRequest;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LabkeepTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin', array $inventories = ['inventario']): User
    {
        return User::factory()->create(['username' => fake()->unique()->userName(), 'role' => $role, 'active' => true, 'allowed_inventories' => $inventories]);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['name' => 'Matraz de prueba', 'category' => 'chemical_materials', 'quantity' => 5, 'unit' => 'piezas', 'details' => []], $extra);
    }

    public function test_headers_cannot_impersonate_a_user(): void
    {
        $this->withHeaders(['X-User' => 'admin', 'X-User-Role' => 'admin'])->postJson('/api/v1/items', $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_login_checks_credentials_and_inactive_users(): void
    {
        $user = $this->user();
        $user->update(['password' => 'ValidPassword123!', 'active' => false]);
        $this->postJson('/api/v1/login', ['username' => $user->username, 'password' => 'ValidPassword123!'])->assertUnprocessable()->assertJsonValidationErrors('username');
        $user->update(['active' => true]);
        $this->postJson('/api/v1/login', ['username' => $user->username, 'password' => 'ValidPassword123!'])->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonMissingPath('user.password');
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_sign_in_without_being_forced_to_change_the_password(): void
    {
        $admin = User::factory()->create([
            'username' => 'site-admin',
            'role' => 'admin',
            'active' => true,
            'password' => 'StrongAdmin2026!',
            'must_change_password' => false,
        ]);

        $this->postJson('/api/v1/login', ['username' => 'site-admin', 'password' => 'StrongAdmin2026!'])
            ->assertOk()
            ->assertJsonPath('user.must_change_password', false);

        $this->actingAs($admin)->getJson('/api/v1/items')->assertOk();
    }

    public function test_temporary_password_blocks_app_until_it_is_changed(): void
    {
        $user = $this->user();
        $user->update(['password' => 'InitialPass2026!', 'must_change_password' => true]);

        $this->postJson('/api/v1/login', ['username' => $user->username, 'password' => 'InitialPass2026!'])
            ->assertOk()
            ->assertJsonPath('user.must_change_password', true);
        $this->getJson('/api/v1/items')->assertStatus(423);
        $this->postJson('/api/v1/password', [
            'current_password' => 'InitialPass2026!',
            'password' => 'FreshAdmin2026!Secure',
            'password_confirmation' => 'FreshAdmin2026!Secure',
        ])->assertOk()->assertJsonPath('user.must_change_password', false);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'must_change_password' => false]);
        $this->getJson('/api/v1/items')->assertOk();
    }

    public function test_user_with_only_non_default_inventory_can_change_temporary_password(): void
    {
        $user = $this->user('responsable', ['oficina']);
        $user->update(['password' => 'InitialPass2026!', 'must_change_password' => true]);

        $this->actingAs($user)->postJson('/api/v1/password', [
            'current_password' => 'InitialPass2026!',
            'password' => 'FreshUser2026!Secure',
            'password_confirmation' => 'FreshUser2026!Secure',
        ])->assertOk()->assertJsonPath('user.must_change_password', false);
    }

    public function test_password_change_errors_explain_current_password_and_symbol_requirements_in_spanish(): void
    {
        $user = $this->user();
        $user->update(['password' => 'InitialPass2026!', 'must_change_password' => true]);
        $this->actingAs($user);

        $this->postJson('/api/v1/password', [
            'current_password' => 'incorrecta',
            'password' => 'FreshAdmin2026!Secure',
            'password_confirmation' => 'FreshAdmin2026!Secure',
        ])->assertUnprocessable()->assertJsonFragment(['La contraseña actual no coincide.']);

        $this->postJson('/api/v1/password', [
            'current_password' => 'InitialPass2026!',
            'password' => 'FreshAdmin2026',
            'password_confirmation' => 'FreshAdmin2026',
        ])->assertUnprocessable()->assertJsonFragment(['La contraseña nueva debe incluir al menos un símbolo, por ejemplo ! o #.']);
    }

    public function test_inactive_sessions_are_forbidden(): void
    {
        $u = $this->user();
        $u->update(['active' => false]);
        $this->actingAs($u)->getJson('/api/v1/items')->assertForbidden();
    }

    public function test_inventory_access_and_record_scope_are_enforced(): void
    {
        $item = InventoryItem::factory()->create(['inventory' => 'sistemas']);
        $this->actingAs($this->user('responsable'))->getJson('/api/v1/items/'.$item->id)->assertNotFound();
        $this->withHeaders(['X-Inventory-Id' => 'sistemas'])->getJson('/api/v1/items')->assertForbidden();
    }

    public function test_admin_creates_item_with_audit_and_server_owned_fields(): void
    {
        $this->actingAs($this->user())->postJson('/api/v1/items', $this->payload(['inventory' => 'sistemas', 'legacy_id' => 999, 'version' => 999]))->assertCreated()->assertJsonPath('pending', false);
        $this->assertDatabaseHas('inventory_items', ['name' => 'Matraz de prueba', 'inventory' => 'inventario', 'legacy_id' => null, 'version' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Registro', 'entity' => 'inventory_item']);
    }

    public function test_negative_stock_and_unknown_details_are_rejected(): void
    {
        $this->actingAs($this->user())->postJson('/api/v1/items', $this->payload(['quantity' => -1]))->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson('/api/v1/items', $this->payload(['details' => ['role' => 'admin']]))->assertUnprocessable()->assertJsonValidationErrors('details');
        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_responsable_change_requires_review(): void
    {
        $responsable = $this->user('responsable');
        $this->actingAs($responsable)->postJson('/api/v1/items', $this->payload())->assertCreated()->assertJsonPath('pending', true);
        $this->assertDatabaseCount('inventory_items', 0);
        $change = ChangeRequest::firstOrFail();
        $this->putJson('/api/v1/changes/'.$change->id, ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->user())->putJson('/api/v1/changes/'.$change->id, ['decision' => 'approved'])->assertOk();
        $this->assertDatabaseHas('inventory_items', ['name' => 'Matraz de prueba']);
        $this->putJson('/api/v1/changes/'.$change->id, ['decision' => 'approved'])->assertConflict();
        $this->assertDatabaseCount('inventory_items', 1);
    }

    public function test_stale_edits_do_not_overwrite_changes(): void
    {
        $item = InventoryItem::factory()->create(['version' => 3]);
        $this->actingAs($this->user())->putJson('/api/v1/items/'.$item->id, $this->payload(['version' => 2]))->assertConflict();
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'version' => 3, 'quantity' => 10]);
    }

    public function test_loan_approval_and_return_are_atomic_and_not_repeatable(): void
    {
        $item = InventoryItem::factory()->create(['quantity' => 2]);
        $borrower = $this->user('responsable');
        $id = $this->actingAs($borrower)->postJson('/api/v1/loans', ['items' => [['id' => $item->id, 'quantity' => 2]]])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/loans/'.$id.'/approve')->assertForbidden();
        $admin = $this->user();
        $this->actingAs($admin)->postJson('/api/v1/loans/'.$id.'/approve')->assertOk();
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'quantity' => 0]);
        $this->postJson('/api/v1/loans/'.$id.'/approve')->assertConflict();
        $this->actingAs($borrower)->postJson('/api/v1/loans/'.$id.'/request-return')->assertOk();
        $this->actingAs($admin)->postJson('/api/v1/loans/'.$id.'/return')->assertOk();
        $this->postJson('/api/v1/loans/'.$id.'/return')->assertConflict();
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'quantity' => 2]);
        $this->assertDatabaseHas('loans', ['id' => $id, 'status' => 'returned']);
    }

    public function test_second_loan_cannot_overdraw_stock(): void
    {
        $item = InventoryItem::factory()->create(['quantity' => 1]);
        $u = $this->user();
        $this->actingAs($u);
        $first = $this->postJson('/api/v1/loans', ['items' => [['id' => $item->id, 'quantity' => 1]]])->json('data.id');
        $second = $this->postJson('/api/v1/loans', ['items' => [['id' => $item->id, 'quantity' => 1]]])->json('data.id');
        $this->postJson('/api/v1/loans/'.$first.'/approve')->assertOk();
        $this->postJson('/api/v1/loans/'.$second.'/approve')->assertUnprocessable();
        $this->assertDatabaseHas('loans', ['id' => $second, 'status' => 'pending']);
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'quantity' => 0]);
    }

    public function test_loan_rejects_duplicate_items_and_cross_inventory_items(): void
    {
        $i = InventoryItem::factory()->create(['inventory' => 'sistemas']);
        $this->actingAs($this->user())->postJson('/api/v1/loans', ['items' => [['id' => $i->id, 'quantity' => 1]]])->assertNotFound();
        $i = InventoryItem::factory()->create();
        $this->postJson('/api/v1/loans', ['items' => [['id' => $i->id, 'quantity' => 1], ['id' => $i->id, 'quantity' => 1]]])->assertUnprocessable();
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_legacy_qr_resolves_only_in_selected_inventory(): void
    {
        $item = InventoryItem::factory()->create(['category' => 'substances', 'legacy_id' => 15]);
        $this->actingAs($this->user())->postJson('/api/v1/scan', ['code' => 'LAB-SUB-15'])->assertOk()->assertJsonPath('data.id', $item->id);
        $this->postJson('/api/v1/scan', ['code' => 'LK:sistemas:'.$item->id])->assertNotFound();
    }

    public function test_uploads_reject_executable_content_and_protect_inventory(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user())->post('/api/v1/attachments', ['file' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')], ['Accept' => 'application/json'])->assertUnprocessable();
        Storage::disk('local')->put('attachments/sistemas/photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6ZdMAAAAASUVORK5CYII='));
        $this->actingAs($this->user('responsable'))->get('/files/sistemas/photo.png')->assertNotFound();

        Storage::disk('local')->put('attachments/inventario/photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6ZdMAAAAASUVORK5CYII='));
        $temporaryUser = $this->user();
        $temporaryUser->update(['must_change_password' => true]);
        $this->actingAs($temporaryUser)->get('/files/inventario/photo.png')->assertNotFound();
    }

    public function test_responsable_cannot_manage_users_or_read_audit(): void
    {
        $this->actingAs($this->user('responsable'))->getJson('/api/v1/users')->assertForbidden();
        $this->getJson('/api/v1/history')->assertForbidden();
    }

    public function test_csv_neutralizes_spreadsheet_formulas(): void
    {
        InventoryItem::factory()->create(['name' => '=1+1']);
        $response = $this->actingAs($this->user())->get('/api/v1/export')->assertOk();
        $this->assertStringContainsString("'=1+1", $response->streamedContent());
    }

    public function test_deletion_with_active_loan_is_blocked(): void
    {
        $i = InventoryItem::factory()->create();
        $this->actingAs($this->user());
        $id = $this->postJson('/api/v1/loans', ['items' => [['id' => $i->id, 'quantity' => 1]]])->json('data.id');
        $this->postJson('/api/v1/loans/'.$id.'/approve')->assertOk();
        $this->deleteJson('/api/v1/items/'.$i->id, ['version' => 2])->assertConflict();
        $this->assertDatabaseHas('inventory_items', ['id' => $i->id, 'deleted_at' => null]);
    }

    public function test_legacy_import_preserves_fields_without_modifying_source(): void
    {
        $dir = storage_path('framework/testing/legacy-'.uniqid());
        mkdir($dir, 0777, true);
        $path = $dir.'/inventario.db';
        $pdo = new \PDO('sqlite:'.$path);
        $pdo->exec('CREATE TABLE substances (id INTEGER PRIMARY KEY, name TEXT, quantity REAL, unit TEXT, stock_units INTEGER, custom_field TEXT)');
        $pdo->exec("INSERT INTO substances VALUES (7,'Reactivo de prueba',250,'ml',3,'preservar')");
        $pdo->exec('CREATE TABLE consulta_compatibility (group_name TEXT PRIMARY KEY, value TEXT)');
        $pdo->exec("INSERT INTO consulta_compatibility VALUES ('A','Texto')");
        $pdo = null;
        $before = hash_file('sha256', $path);
        try {
            $this->artisan('labkeep:import-legacy', ['directory' => $dir])->assertSuccessful();
            $this->assertDatabaseCount('inventory_items', 0);
            $this->artisan('labkeep:import-legacy', ['directory' => $dir, '--apply' => true])->assertSuccessful();
            $i = InventoryItem::firstOrFail();
            $this->assertSame('3.000', $i->quantity);
            $this->assertSame('envases', $i->unit);
            $this->assertSame('preservar', $i->details['custom_field']);
            $this->assertDatabaseCount('legacy_records', 2);
            $this->assertSame($before, hash_file('sha256', $path));
            $this->artisan('labkeep:import-legacy', ['directory' => $dir, '--apply' => true])->assertFailed();
            $this->assertDatabaseCount('inventory_items', 1);
        } finally {
            unlink($path);
            rmdir($dir);
        }
    }

    public function test_portable_export_refuses_to_overwrite_and_round_trips(): void
    {
        $u = $this->user();
        $i = InventoryItem::factory()->create(['details' => ['marca' => 'Prueba']]);
        $file = storage_path('framework/testing/transfer-'.uniqid().'.json');
        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        try {
            $this->artisan('labkeep:transfer', ['action' => 'export', 'file' => $file])->assertSuccessful();
            $this->artisan('labkeep:transfer', ['action' => 'export', 'file' => $file])->assertFailed();
            $this->artisan('labkeep:transfer', ['action' => 'import', 'file' => $file])->assertFailed();
            DB::table('inventory_items')->delete();
            DB::table('users')->delete();
            $this->artisan('labkeep:transfer', ['action' => 'import', 'file' => $file])->assertSuccessful();
            $this->assertDatabaseHas('inventory_items', ['id' => $i->id, 'name' => $i->name]);
            $this->assertSame('Prueba', InventoryItem::find($i->id)->details['marca']);
            $this->assertTrue(Hash::check('password', User::find($u->id)->password));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
