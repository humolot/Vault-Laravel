<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('color', 9)->default('#3b82f6');
            $table->text('public_key');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('member'); // manager | member
            $table->text('wrapped_key')->nullable(); // chave privada do grupo selada p/ o membro
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30)->default('login');
            $table->longText('payload'); // JSON cifrado com a chave do item
            $table->unsignedSmallInteger('rotation_days')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('rotation_flagged_at')->nullable(); // acesso revogado → sugerir troca
            $table->timestamp('rotation_snoozed_until')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['owner_id', 'deleted_at']);
        });

        Schema::create('item_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('recipient_type', 10); // user | group
            $table->unsignedBigInteger('recipient_id');
            $table->string('permission', 10)->default('view'); // owner | edit | view
            $table->text('wrapped_key')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['item_id', 'recipient_type', 'recipient_id']);
            $table->index(['recipient_type', 'recipient_id']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('item_id')->constrained('items')->cascadeOnDelete();
            $table->text('meta'); // nome original + mime, cifrados com a chave do item
            $table->unsignedBigInteger('size');
            $table->string('path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('credential_id', 512)->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->string('item_id', 26)->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->text('meta')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('passkeys');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('item_keys');
        Schema::dropIfExists('items');
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
    }
};
