<?php

use Illuminate\Support\Facades\Schedule;

// Jalankan setiap hari jam 00:05 untuk menandai alpha mahasiswa yang tidak hadir kemarin
Schedule::command('attendance:mark-alpha')->dailyAt('00:05');
