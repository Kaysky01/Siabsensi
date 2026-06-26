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
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('username', 50)->index('idx_la_username');
            $table->string('ip_address', 45)->nullable()->index('idx_la_ip');
            $table->tinyInteger('success')->nullable()->default(0);
            $table->timestamp('attempted_at')->useCurrent()->index('idx_la_attempted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
