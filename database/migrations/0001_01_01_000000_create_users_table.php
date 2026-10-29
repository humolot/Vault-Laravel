<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role', 20)->default('user'); // admin | user
            $table->boolean('is_active')->default(true);

            // Fator 1 e 2: senha + frase de segurança (hashes)
            $table->string('password')->nullable();
            $table->string('phrase_hash')->nullable();

            // Chaves do cofre
            $table->text('public_key')->nullable();
            $table->string('kdf_salt_password')->nullable();
            $table->string('kdf_salt_phrase')->nullable();
            $table->text('private_key_enc')->nullable();
            $table->text('recovery_private_key_enc')->nullable();

            // Fator 3: TOTP obrigatório
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->unsignedBigInteger('totp_last_step')->nullable();

            // Passkey (opcional, fator extra)
            $table->boolean('passkey_required')->default(false);

            // Convite / ativação
            $table->string('invite_token_hash', 64)->nullable()->index();
            $table->timestamp('invite_expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();

            // Proteção contra força bruta
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            // Preferências
            $table->string('locale', 5)->default('pt_BR');
            $table->string('theme', 10)->default('dark');
            $table->unsignedSmallInteger('rotation_days')->default(90);
            $table->unsignedSmallInteger('access_review_days')->default(180);

            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
