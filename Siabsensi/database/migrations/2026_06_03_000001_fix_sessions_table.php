<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old custom sessions table
        Schema::dropIfExists('sessions');

        // Create Laravel's default sessions table structure
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');

        // Restore the old custom sessions table structure
        Schema::create('sessions', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id')->index('idx_user');
            $table->string('session_token', 255)->unique('session_token');
            $table->index('session_token', 'idx_token');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('expires_at')->index('idx_expires');
            $table->timestamp('created_at')->useCurrent();
        });
    }
};
