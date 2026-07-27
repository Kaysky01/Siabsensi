<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kompi_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('kompi')->index();
            $table->string('judul')->default('Pengumuman Garda');
            $table->text('pesan')->nullable();
            $table->string('link_wa')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kompi_announcements');
    }
};
