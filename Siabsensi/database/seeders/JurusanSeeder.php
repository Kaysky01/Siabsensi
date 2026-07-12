<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JurusanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Seeder ini membuat data jurusan sesuai dengan template kartu yang tersedia
     * di folder: public/static/img/{nama_jurusan}/
     */
    public function run(): void
    {
        $jurusanList = [
            'Budidaya Tanaman Pangan',
            'Budidaya Tanaman Perkebunan',
            'Ekonomi dan Bisnis',
            'Perikanan dan Kelautan',
            'Perternakan',
            'Teknik',
            'Teknologi Informasi',
            'Teknologi Pertanian',
        ];

        foreach ($jurusanList as $namaJurusan) {
            Jurusan::firstOrCreate(
                ['nama' => $namaJurusan],
                ['nama' => $namaJurusan]
            );
        }

        $this->command->info('✅ Seeder Jurusan berhasil! Total: ' . count($jurusanList) . ' jurusan');
        
        // Tampilkan list jurusan yang sudah dibuat
        $this->command->info('📋 Daftar Jurusan:');
        foreach ($jurusanList as $index => $nama) {
            $this->command->info('   ' . ($index + 1) . '. ' . $nama);
        }
    }
}
