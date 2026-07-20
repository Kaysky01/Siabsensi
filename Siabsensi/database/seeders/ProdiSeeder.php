<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use App\Models\Prodi;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProdiSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Teknologi Informasi' => [
                'Manajemen Informatika',
                'Teknologi Rekayasa Internet',
                'Teknologi Rekayasa Perangkat Lunak',
                'Teknologi Rekayasa Elektronika',
                'Sains Data Terapan',
            ],
            'Perikanan dan Kelautan' => [
                'Budidaya Perikanan',
                'Perikanan Tangkap',
                'Teknologi Pembenihan Ikan',
                'Teknologi Akuakultur',
                'Teknologi Cerdas Penangkapan Ikan',
            ],
            'Teknik' => [
                'Teknik Sumberdaya Lahan dan Lingkungan',
                'Teknologi Rekayasa Konstruksi Jalan dan Jembatan',
                'Teknologi Rekayasa Kimia Industri',
                'Teknologi Rekayasa Otomotif',
            ],
            'Ekonomi dan Bisnis' => [
                'Perjalanan Wisata',
                'Akuntansi',
                'Agribisnis Pangan',
                'Pengelolaan Agribisnis',
                'Akuntansi Perpajakan',
                'Akuntansi Bisnis Digital',
                'Pengelolaan Perhotelan',
                'Pengelolaan Konvensi dan Acara',
                'Bahasa Inggris untuk Komunikasi Bisnis dan Profesional',
                'Produksi Media',
                'Bisnis Digital',
            ],
            'Peternakan' => [
                'Teknologi Pakan Ternak',
                'Teknologi Produksi Ternak',
                'Agribisnis Peternakan',
            ],
            'Teknologi Pertanian' => [
                'Mekanisasi Pertanian',
                'Teknologi Pangan',
                'Pengembangan Produk Agroindustri',
                'Kimia Terapan',
                'Teknologi Pangan Halal',
                'Gizi Klinis',
            ],
            'Budidaya Tanaman Perkebunan' => [
                'Produksi Tanaman Perkebunan',
                'Produksi dan Manajemen Industri Perkebunan',
                'Pengelolaan Perkebunan Kopi',
                'Teknologi Produksi Tanaman Perkebunan',
            ],
            'Budidaya Tanaman Pangan' => [
                'Hortikultura',
                'Teknologi Perbenihan',
                'Teknologi Produksi Tanaman Pangan',
                'Teknologi Produksi Tanaman Hortikultura',
            ],
        ];

        $totalProdi = 0;

        foreach ($data as $namaJurusan => $prodiList) {
            $jurusan = Jurusan::where('nama', $namaJurusan)->first();

            if (!$jurusan) {
                $this->command->warn("Jurusan '{$namaJurusan}' tidak ditemukan, dilewati.");
                continue;
            }

            foreach ($prodiList as $namaProdi) {
                Prodi::firstOrCreate(
                    ['jurusan_id' => $jurusan->id, 'nama' => $namaProdi],
                    ['jurusan_id' => $jurusan->id, 'nama' => $namaProdi]
                );
                $totalProdi++;
            }

            $this->command->info("  {$namaJurusan}: " . count($prodiList) . " prodi");
        }

        $this->command->info("Seeder Prodi berhasil! Total: {$totalProdi} prodi");
    }
}
