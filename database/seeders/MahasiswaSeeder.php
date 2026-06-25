<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MahasiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        $kompi = ['A', 'B', 'C', 'D', 'E', 'F'];
        $jurusan = ['Teknik Informatika', 'Sistem Informasi', 'Manajemen Informatika', 'Teknik Elektro', 'Teknik Mesin'];
        $prodi = [
            'Manajemen Informatika', 'Teknologi Rekayasa Internet', 'Teknologi Rekayasa Perangkat Lunak', 
            'Teknologi Rekayasa Elektronika', 'Sains Data Terapan', 'Budidaya Perikanan', 'Perikanan Tangkap', 
            'Teknologi Pembenihan Ikan', 'Teknologi Akuakultur', 'Teknologi Cerdas Penangkapan Ikan', 
            'Teknik Sumberdaya Lahan dan Lingkungan', 'Teknologi Rekayasa Kontruksi Jalan dan Jembatan', 
            'Teknologi Rekayasa Kimia Industri', 'Teknologi Rekayasa Otomotif', 'Perjalanan Wisata', 
            'Agribisnis Pangan', 'Pengelolaan Agribisnis', 'Akuntansi Perpajakan', 'Akuntansi Bisnis Digital', 
            'Pengelolaan Perhotelan', 'Pengelolaan Konvensi dan Acara', 'Bahasa Inggris untuk Komunikasi Bisnis dan Profesional', 
            'Produksi Media', 'Bisnis Digital', 'Teknologi Pakan Ternak', 'Teknologi Produksi Ternak', 
            'Agribisnis Peternakan', 'Mekanisasi Pertanian', 'Teknologi Pangan', 'Pengembangan Produk Agroindustri', 
            'Kimia Terapan', 'Teknologi Pangan Halal', 'Gizi Klinis', 'Produksi Tanaman Perkebunan', 
            'Produksi dan Manajemen Industri Perkebunan', 'Pengelolaan Perkebunan Kopi', 
            'Teknologi Produksi Tanaman Perkebunan', 'Hortikultura', 'Teknologi Perbenihan', 
            'Teknologi Produksi Tanaman Pangan', 'Teknologi Produksi Tanaman Hortikultura'
        ];

        $mahasiswaData = [];
        for ($i = 1; $i <= 100; $i++) {
            $nim = 'MHS' . str_pad($i, 3, '0', STR_PAD_LEFT);
            $jurusanAcak = $faker->randomElement($jurusan);
            $prodiAcak = $faker->randomElement($prodi);

            $mahasiswaData[] = [
                'id' => $nim,
                'name' => $faker->name(),
                'kompi' => $faker->randomElement($kompi),
                'jurusan' => $jurusanAcak,
                'prodi' => $prodiAcak,
                'email' => strtolower(str_replace(' ', '', $nim)) . '@mail.test',
                'no_telp_mahasiswa' => $faker->numerify('08##########'),
                'no_telp_ortu' => $faker->numerify('08##########'),
                'qr_code_id' => 'QR-' . $nim,
                'created_at' => Carbon::now()->subDays(rand(1, 365)),
                'is_active' => $faker->boolean(90),
            ];
        }

        DB::table('mahasiswa')->insert($mahasiswaData);

        // Auto-create users for these mahasiswa
        $userData = [];
        foreach ($mahasiswaData as $mhs) {
            $userData[] = [
                'username' => $mhs['id'],
                'password_hash' => Hash::make('123456'),
                'full_name' => $mhs['name'],
                'email' => $mhs['email'],
                'role' => 'mahasiswa',
                'mahasiswa_id' => $mhs['id'],
                'is_active' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }
        DB::table('users')->insert($userData);
    }
}
