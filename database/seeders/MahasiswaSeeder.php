<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MahasiswaSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('mahasiswa')->updateOrInsert(
            ['id' => 'MHS-001'],
            [
                'name'              => 'Budi Mahasiswa',
                'kelompok'          => 'Kelompok A',
                'jurusan'           => 'Teknik Informatika',
                'email'             => 'budi@mhs.test',
                'no_telp_mahasiswa' => '081234567890',
                'no_telp_ortu'      => '080987654321',
                'qr_code_id'        => 'QR-MHS-001',
                'created_at'        => Carbon::now(),
                'is_active'         => 1,
            ]
        );
    }
}