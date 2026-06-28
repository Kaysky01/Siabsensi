<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurusan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('prodi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurusan_id')->constrained('jurusan')->onDelete('cascade');
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('kompi', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('garda_id')->nullable(); // Reference to users.username
            $table->timestamps();

            $table->foreign('garda_id')->references('username')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kompi');
        Schema::dropIfExists('prodi');
        Schema::dropIfExists('jurusan');
    }
};
