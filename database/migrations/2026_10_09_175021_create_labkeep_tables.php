<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username')->nullable()->unique();
            $t->string('role')->default('responsable');
            $t->boolean('active')->default(true);
            $t->json('allowed_inventories')->nullable();
        });
        Schema::create('inventory_items', function (Blueprint $t) {
            $t->id();
            $t->string('inventory', 32);
            $t->string('category', 40);
            $t->unsignedBigInteger('legacy_id')->nullable();
            $t->string('name');
            $t->decimal('quantity', 14, 3)->default(0);
            $t->string('unit', 40)->default('piezas');
            $t->string('location')->nullable();
            $t->date('expiration_date')->nullable();
            $t->text('qr_content')->nullable();
            $t->json('details')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['inventory', 'category', 'legacy_id']);
            $t->index(['inventory', 'category', 'deleted_at']);
            $t->index(['inventory', 'expiration_date']);
        });
        Schema::create('loans', function (Blueprint $t) {
            $t->id();
            $t->string('inventory', 32);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('borrower_name');
            $t->json('items');
            $t->string('status', 40)->default('pending');
            $t->text('notes')->nullable();
            $t->text('feedback')->nullable();
            $t->string('return_photo')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('returned_at')->nullable();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['inventory', 'status']);
        });
        Schema::create('change_requests', function (Blueprint $t) {
            $t->id();
            $t->string('inventory', 32);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 20);
            $t->json('payload');
            $t->unsignedInteger('base_version')->nullable();
            $t->string('status', 20)->default('pending');
            $t->text('feedback')->nullable();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['inventory', 'status']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->string('inventory', 32);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('actor');
            $t->string('action');
            $t->string('entity');
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->timestamps();
            $t->index(['inventory', 'created_at']);
        });
        Schema::create('legacy_records', function (Blueprint $t) {
            $t->id();
            $t->string('inventory', 32);
            $t->string('source_table', 64);
            $t->string('source_id', 64);
            $t->json('data');
            $t->timestamps();
            $t->unique(['inventory', 'source_table', 'source_id']);
        });
    }

    public function down(): void
    {
        foreach (['legacy_records', 'audit_logs', 'change_requests', 'loans', 'inventory_items'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['username', 'role', 'active', 'allowed_inventories']);
        });
    }
};
