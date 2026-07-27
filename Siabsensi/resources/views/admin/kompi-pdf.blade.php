<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a2e; }

    .header { text-align: center; border-bottom: 3px solid #1e3a8a; margin-bottom: 14px; padding-bottom: 10px; }
    .header h1 { font-size: 16px; font-weight: bold; color: #1e3a8a; margin: 0 0 4px 0; }
    .header .subtitle { font-size: 9px; color: #555; }

    .summary-box {
        border: 1px solid #cbd5e1;
        padding: 5px 10px;
        margin-bottom: 12px;
        background: #f8faff;
        font-size: 9px;
        color: #475569;
    }

    .kompi-section { margin-bottom: 16px; }

    .kompi-title {
        background: #1e3a8a;
        color: #fff;
        padding: 4px 8px;
        font-size: 11px;
        font-weight: bold;
        margin-bottom: 4px;
        width: 100%;
    }

    table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    thead tr th {
        background: #1e40af;
        color: #fff;
        padding: 4px 5px;
        text-align: left;
        font-size: 9px;
        font-weight: bold;
    }
    tbody tr td {
        padding: 3px 5px;
        font-size: 9px;
        border-bottom: 1px solid #e2e8f0;
        color: #1e293b;
    }
    .alt-row td { background: #eef2ff; }

    .col-no    { width: 4%; text-align: center; }
    .col-id    { width: 14%; }
    .col-name  { width: 22%; }
    .col-jur   { width: 20%; }
    .col-prodi { width: 22%; }
    .col-telp  { width: 18%; }

    .footer-left  { text-align: left;  font-size: 8px; color: #888; margin-top: 10px; border-top: 1px solid #cbd5e1; padding-top: 4px; }
    .footer-right { text-align: right; font-size: 8px; color: #888; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $title }}</h1>
    <div class="subtitle">
        Sistem Informasi Absensi PKKMB &nbsp;|&nbsp;
        Dicetak: {{ now()->format('d M Y, H:i') }} WIB
    </div>
</div>

<div class="summary-box">
    Total mahasiswa: <strong>{{ $mahasiswaList->count() }}</strong>
    &nbsp;|&nbsp;
    Total kompi: <strong>{{ $grouped->count() }}</strong>
    @if($kompiFilter && $kompiFilter !== 'all')
    &nbsp;|&nbsp; Kompi: <strong>{{ $kompiFilter }}</strong>
    @endif
</div>

@foreach($grouped as $kompiName => $siswa)
<div class="kompi-section">
    <div class="kompi-title">
        {{ $kompiName ?: '(Belum Ada Kompi)' }} &nbsp;&mdash;&nbsp; {{ $siswa->count() }} mahasiswa
    </div>
    <table>
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-id">ID / NPM</th>
                <th class="col-name">Nama Mahasiswa</th>
                <th class="col-jur">Jurusan</th>
                <th class="col-prodi">Prodi</th>
                <th class="col-telp">No. Telp</th>
            </tr>
        </thead>
        <tbody>
            @foreach($siswa as $no => $m)
            <tr class="{{ $no % 2 === 1 ? 'alt-row' : '' }}">
                <td class="col-no">{{ $no + 1 }}</td>
                <td class="col-id">{{ $m->id }}</td>
                <td class="col-name">{{ $m->name }}</td>
                <td class="col-jur">{{ $m->jurusan ?? '-' }}</td>
                <td class="col-prodi">{{ $m->prodi ?? '-' }}</td>
                <td class="col-telp">{{ $m->no_telp_mahasiswa ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endforeach

<div class="footer-left">Sistem Informasi Absensi PKKMB &copy; {{ now()->year }}</div>
<div class="footer-right">{{ $namaFile }}.pdf</div>

</body>
</html>
