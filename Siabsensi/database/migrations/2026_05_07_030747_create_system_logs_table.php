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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('level', 20)->nullable()->index('idx_level');
            $table->text('message')->nullable();
            $table->string('camera_id', 50)->nullable();
            $table->timestamp('timestamp')->useCurrent()->index('idx_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
