<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ubah kolom hari_ke dari integer ke varchar(50)
     * agar dapat menampung label seperti "Hari 1", "Day 1", "Pre-PKKMB", dst.
     */
    public function up(): void
    {
        Schema::table('pkkmb_schedules', function (Blueprint $table) {
            $table->string('hari_ke', 50)->comment('Label PKKMB, misal: 1, Hari 1, Pre-PKKMB, dst')->change();
        });
    }

    public function down(): void
    {
        // Convert back to integer (casting string to int, values with letters become 0)
        DB::statement("UPDATE pkkmb_schedules SET hari_ke = CASE WHEN hari_ke REGEXP '^[0-9]+$' THEN CAST(hari_ke AS UNSIGNED) ELSE 0 END");
        Schema::table('pkkmb_schedules', function (Blueprint $table) {
            $table->integer('hari_ke')->comment('PKKMB Hari ke-1, 2, 3, dst')->change();
        });
    }
};
