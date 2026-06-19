<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MahasiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Panggil seeder lain jika diperlukan (misal: UserSeeder agar ada relasi)
        // $this->call(UserSeeder::class);

        $faker = Faker::create('id_ID'); // Gunakan locale Indonesia

        $mahasiswaData = [];
        $kompi = ['A', 'B', 'C', 'D', 'E', 'F'];
        $jurusan = ['Teknik Informatika', 'Sistem Informasi', 'Manajemen Informatika', 'Teknik Elektro', 'Teknik Mesin'];
        $prodi = [
            'D3 Teknik Informatika', 'S1 Teknik Informatika', 'D3 Sistem Informasi', 'S1 Sistem Informasi',
            'D3 Manajemen Informatika', 'S1 Teknik Elektro', 'D3 Teknik Elektro', 'S1 Teknik Mesin',
        ];

        $existingIds = DB::table('mahasiswa')->pluck('id')->toArray();
        $currentIdCount = count($existingIds);

        for ($i = 1; $i <= 100; $i++) { // Generate 100 mahasiswa data
            $nim = 'MHS-'.str_pad(++$currentIdCount, 3, '0', STR_PAD_LEFT);
            // Pastikan NIM unik, meskipun loop ini untuk seeder saja, ini praktik yang baik
            while (in_array($nim, $existingIds)) {
                $nim = 'MHS-'.str_pad(++$currentIdCount, 3, '0', STR_PAD_LEFT);
            }
            $existingIds[] = $nim;

            $jurusanAcak = $faker->randomElement($jurusan);
            $prodiAcak = $faker->randomElement($prodi);
            // Jika jurusan tidak punya Prodi yang sesuai, bisa set default atau ambil dari list yang lebih spesifik
            if (! str_contains($jurusanAcak, $prodiAcak)) {
                $prodiAcak = $faker->randomElement(array_filter($prodi, fn ($p) => str_contains($p, substr($jurusanAcak, 0, 3))));
            }

            $mahasiswaData[] = [
                'id' => $nim,
                'name' => $faker->name(),
                'kompi' => $faker->randomElement($kompi),
                'jurusan' => $jurusanAcak,
                'prodi' => $prodiAcak,
                'email' => strtolower(str_replace(' ', '', $nim)).'@mail.test', // Email unik berdasarkan NIM
                'no_telp_mahasiswa' => $faker->numerify('08##########'),
                'no_telp_ortu' => $faker->numerify('08##########'),
                'qr_code_id' => 'QR-'.$nim,
                'created_at' => Carbon::now()->subDays(rand(1, 365)), // Tanggal dibuat acak setahun terakhir
                'is_active' => $faker->boolean(90), // 90% aktif, 10% tidak aktif
            ];
        }

        // Masukkan data ke tabel, gunakan insert untuk efisiensi jika data banyak
        DB::table('mahasiswa')->insert($mahasiswaData);

        // Jika Anda juga ingin membuat user untuk mahasiswa ini, Anda perlu logic tambahan
        // Contoh: Loop lagi dan buat user dengan password default (harus di-hash)
    }
}
