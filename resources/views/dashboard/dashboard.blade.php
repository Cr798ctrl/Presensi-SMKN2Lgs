@extends('layouts.presensi')

@section('content')
@php
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Storage;

    // Set locale ke Indonesia
    Carbon::setLocale('id');

    // 1. Durasi kerja standar per hari dalam jam (Sesuai dengan cetaklaporan.blade.php)
    $jamKerjaHarian = [
        Carbon::MONDAY => 7,      // Senin (1)
        Carbon::TUESDAY => 7,    // Selasa (2)
        Carbon::WEDNESDAY => 7,   // Rabu (3)
        Carbon::THURSDAY => 6.5, // Kamis (4)
        Carbon::FRIDAY => 4,      // Jumat (5)
        Carbon::SATURDAY => 6,    // Sabtu (6)
        Carbon::SUNDAY => 0,      // Minggu (0)
    ];

    // --- INISIALISASI VARIABEL PENTING (DIJAMIN TIDAK UNDEFINED) ---
    // Inisialisasi variabel bulanan
    $totalJamKerjaStandar = 0; // Mengikuti $totalJamSeharusnya di laporan (NILAI FINAL)
    $totalJamHadirBulanan = 0; // Mengikuti $totalJamHadir di laporan (NILAI FINAL SETELAH CAPPING MINGGUAN)
    // <<< MODIFIKASI DIMULAI DI SINI: Variabel Tampilan Real-Time
    $totalJamKerjaStandarTampilan = 0; // BARU: Nilai total jam kerja standar untuk tampilan (Real-Time)
    $totalJamHadirBulananTampilan = 0; // BARU: Nilai total jam hadir untuk tampilan (Real-Time)
    // MODIFIKASI SELESAI >>>
    $hadirBulanan = 0;
    $izinBulanan = 0; // Dipisah dari sakit
    $sakitBulanan = 0; // Dipisah dari izin
    $cutiBulanan = 0;
    $dinasLuarBulanan = 0; // BARU: Menambahkan Dinas Luar
    $alpaBulanan = 0;

    // Inisialisasi variabel mingguan untuk CAPPING
    $jamHadirMingguIni = 0;
    $jamSeharusnyaMingguIni = 0;

    // Safety Check: Pastikan variabel yang dikirim dari controller adalah array/collection
    $historibulanini = $historibulanini ?? []; 
    $harilibur = $harilibur ?? []; 
    
    $tempPresensi = collect($historibulanini);
    $tempHarilibur = collect($harilibur); // Sekarang ini aman

    // Tentukan periode (1 s/d Hari Ini)
    $startOfMonth = now()->startOfMonth();
    $endOfMonth = now(); 
    $jamMasukKantor = '07:30:00'; 
    // --- AKHIR INISIALISASI ---


    // --- LOGIKA PERHITUNGAN MENGGUNAKAN LOOPING HARIAN UNTUK MEREPLIKASI CAPPING MINGGUAN ---
    for ($date = $startOfMonth->copy(); $date->lte($endOfMonth); $date->addDay()) {
        $dayOfWeek = $date->dayOfWeek;
        $presensiHariIni = $tempPresensi->where('tgl_presensi', $date->format('Y-m-d'))->first();
        $liburData = $tempHarilibur->where('tanggal_libur', $date->format('Y-m-d'))->first();
        
        $isHoliday = !is_null($liburData);
        $isWeekend = $date->isSunday();
        $isEndOfWeekWork = $date->isSaturday() || $date->isSameDay($endOfMonth);

        // --- 1. Logika Hari Libur/Minggu ---
        if ($isWeekend || $isHoliday) {
            // Cek jika hari ini adalah akhir dari periode akumulasi (Minggu atau Hari Terakhir Laporan)
            if ($date->isSunday() || $date->isSameDay($endOfMonth)) {
                // Lakukan Capping dan Akumulasi sisa dari hari kerja sebelum ini
                if ($jamSeharusnyaMingguIni > 0) {
                    // Akumulasi FINAL ke variabel bulanan (Hanya di Akhir Periode Capping)
                    $totalJamHadirBulanan += min($jamHadirMingguIni, $jamSeharusnyaMingguIni);
                    $totalJamKerjaStandar += $jamSeharusnyaMingguIni;
                    
                    // Reset
                    $jamHadirMingguIni = 0;
                    $jamSeharusnyaMingguIni = 0;
                }
            }
            continue; // Lanjut ke hari berikutnya
        }

        // --- 2. Logika Hari Kerja (Senin - Sabtu) ---
        $jamSeharusnyaHariIni = $jamKerjaHarian[$dayOfWeek];
        $jamHadirHariIni = 0;
        $tambahJamSeharusnya = true; 

        if ($presensiHariIni) {
            // Hitung Rekap Status
            if ($presensiHariIni->status == 'h') {
                $hadirBulanan++;
                
                // Hitung Jam Hadir (hanya jika sudah absen pulang)
                if ($presensiHariIni->jam_in && $presensiHariIni->jam_out) {
                    $jamIn = Carbon::parse($presensiHariIni->jam_in);
                    $jamOut = Carbon::parse($presensiHariIni->jam_out);
                    $durasiMenit = $jamOut->diffInMinutes($jamIn);
                    
                    // Potongan Jumat (Sama dengan cetaklaporan.blade.php)
                    if ($date->dayOfWeek == Carbon::FRIDAY) {
                        $breakStart = Carbon::parse($date->format('Y-m-d') . ' 12:00:00');
                        $breakEnd = Carbon::parse($date->format('Y-m-d') . ' 14:00:00');
                        if ($jamIn->lte($breakStart) && $jamOut->gte($breakEnd)) {
                            $durasiMenit -= 120; 
                        }
                    }
                    $jamHadirHariIni = $durasiMenit / 60;

                } elseif ($presensiHariIni->jam_in && !$presensiHariIni->jam_out) {
                    // Logika Lupa Absen Pulang = 50% Jam Kerja Standar (sesuai cetaklaporan)
                    $jamHadirHariIni = ($jamKerjaHarian[$dayOfWeek] / 2); 
                }
                
                $tambahJamSeharusnya = true; // Hari Hadir dihitung jam standarnya

            } elseif ($presensiHariIni->status == 'i') {
                $izinBulanan++;
                // MODIFIKASI: Izin, Sakit, Cuti DIHITUNG jam standarnya
                $tambahJamSeharusnya = true; 
                // Jam Hadir Izin = 0 (Tidak Hadir Fisik)

            } elseif ($presensiHariIni->status == 's') {
                $sakitBulanan++;
                // MODIFIKASI: Izin, Sakit, Cuti DIHITUNG jam standarnya
                $tambahJamSeharusnya = true; 
                // Jam Hadir Sakit = 0 (Tidak Hadir Fisik)

            } elseif ($presensiHariIni->status == 'c') {
                $cutiBulanan++;
                // MODIFIKASI: Izin, Sakit, Cuti DIHITUNG jam standarnya
                $tambahJamSeharusnya = true; 
                // Jam Hadir Cuti = 0 (Tidak Hadir Fisik)

            } elseif ($presensiHariIni->status == 'd') { // BARU: Dinas Luar
                $dinasLuarBulanan++;
                // MODIFIKASI: Dinas Luar DIHITUNG jam standarnya
                $tambahJamSeharusnya = true; 
                // MODIFIKASI: Dinas Luar DIHITUNG jam hadir sebesar Jam Kerja Standar
                $jamHadirHariIni = $jamKerjaHarian[$dayOfWeek];
            }
        } else {
            // Alpa
            $alpaBulanan++; 
            $tambahJamSeharusnya = true; // Alpa DIHITUNG jam standarnya (sesuai cetaklaporan)
        }
        
        // Akumulasi jam hadir ke variabel mingguan (untuk carry-over)
        $jamHadirMingguIni += $jamHadirHariIni;
        
        // HANYA AKUMULASI JAM SEHARUSNYA JIKA BUKAN IZIN/SAKIT/CUTI/DINAS LUAR
        // Karena status 'i', 's', 'c', 'd' sudah diatur $tambahJamSeharusnya = true,
        // maka semua hari kerja (termasuk I/S/C/D) akan dihitung jam standarnya
        if ($tambahJamSeharusnya) {
            $jamSeharusnyaMingguIni += $jamSeharusnyaHariIni;
        }

        // --- 3. LOGIKA CAPPING MINGGUAN --- (TIDAK BERUBAH DARI KODE ASLI)
        if ($isEndOfWeekWork) {
            if ($jamSeharusnyaMingguIni > 0) {
                // Capping: Ambil nilai terkecil antara jam hadir dan jam seharusnya untuk minggu ini.
                $totalJamHadirBulanan += min($jamHadirMingguIni, $jamSeharusnyaMingguIni);
                
                // Akumulasikan total jam seharusnya ke variabel bulanan
                $totalJamKerjaStandar += $jamSeharusnyaMingguIni; 
                
                // Reset akumulasi mingguan
                $jamHadirMingguIni = 0;
                $jamSeharusnyaMingguIni = 0;
            }
        }
    }
    
    // <<< MODIFIKASI DIMULAI DI SINI: Perhitungan Nilai Tampilan Real-Time (Diluar Loop)
    
    // 1. Akumulasi jam kerja standar untuk tampilan (FINAL + Sisa minggu ini)
    $totalJamKerjaStandarTampilan = $totalJamKerjaStandar + $jamSeharusnyaMingguIni;

    // 2. Akumulasi jam hadir untuk tampilan (FINAL + Sisa minggu ini yang sudah di-cap)
    $jamHadirSisaMingguIni = min($jamHadirMingguIni, $jamSeharusnyaMingguIni);
    $totalJamHadirBulananTampilan = $totalJamHadirBulanan + $jamHadirSisaMingguIni;
    
    // MODIFIKASI SELESAI >>>

    // Hitung Persentase Kehadiran
    // <<< MODIFIKASI DIMULAI DI SINI: Menggunakan Variabel Tampilan
    if ($totalJamKerjaStandarTampilan > 0) {
        $persentase = ($totalJamHadirBulananTampilan / $totalJamKerjaStandarTampilan) * 100;
    // MODIFIKASI SELESAI >>>
        $persentase = min($persentase, 100);
    } else {
        $persentase = 0;
    }

    // Konversi total jam kerja dan total jam hadir ke format jam dan menit (untuk tampilan)
    // Pembulatan mengikuti logika formatJamMenit di cetaklaporan.blade.php
    
    // <<< MODIFIKASI DIMULAI DI SINI: Menggunakan Variabel Tampilan
    $totalJamKerjaJam = floor($totalJamKerjaStandarTampilan);
    $totalJamKerjaMenit = round(($totalJamKerjaStandarTampilan - $totalJamKerjaJam) * 60);
    // MODIFIKASI SELESAI >>>

    // Menangani overflow menit (misal: 60 menit)
    if ($totalJamKerjaMenit >= 60) {
        $totalJamKerjaJam += floor($totalJamKerjaMenit / 60);
        $totalJamKerjaMenit %= 60;
    }

    // <<< MODIFIKASI DIMULAI DI SINI: Menggunakan Variabel Tampilan
    $totalJamHadirJam = floor($totalJamHadirBulananTampilan);
    $totalJamHadirMenit = round(($totalJamHadirBulananTampilan - $totalJamHadirJam) * 60);
    // MODIFIKASI SELESAI >>>

    // Menangani overflow menit (misal: 60 menit)
    if ($totalJamHadirMenit >= 60) {
        $totalJamHadirJam += floor($totalJamHadirMenit / 60);
        $totalJamHadirMenit %= 60;
    }


    // Variabel untuk Looping Histori Harian (di bagian bawah)
    $bulanTampil = $bulanini ?? now()->month;
    $tahunTampil = $tahunini ?? now()->year;
    
    $tanggalAwalLoop = Carbon::createFromDate($tahunTampil, $bulanTampil, 1)->startOfDay();
    $tanggalAkhirLoop = (now()->month == $bulanTampil && now()->year == $tahunTampil) 
                        ? Carbon::now()->startOfDay() 
                        : $tanggalAwalLoop->copy()->endOfMonth()->startOfDay();

@endphp

<style>
    /* Tambahkan style yang diperlukan di sini. Saya akan menyertakan style yang umum agar tampilan tetap baik. */
    body {
        background-color: #eef2f7;
        font-family: 'Segoe UI', sans-serif;
    }

    .logout {
        position: absolute;
        color: #616161;
        font-size: 30px;
        text-decoration: none;
        right: 8px;
    }

    .logout:hover {
        color: #212121;
    }
    
    /* MODIFIKASI: Menambahkan style untuk logo */
    .logo-header {
        position: absolute;
        right: 50px; /* Jarak dari kanan, di sebelah tombol logout */
        top: 20px; /* Sejajar dengan tombol logout */
        width: 35px; /* Sesuaikan ukuran logo */
        height: 35px; /* Sesuaikan ukuran logo */
        z-index: 10;
        cursor: pointer;
    }
    /* AKHIR MODIFIKASI */

    .card {
        background-color: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(6px);
        border-radius: 12px;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        border: none;
    }

    .gradasigreen {
        background: linear-gradient(135deg, #43A047, #2E7D32);
        color: white;
    }

    .gradasired {
        background: linear-gradient(135deg, #FB8C00, #E64A19);
        color: white;
    }

    .green {
        color: #1565C0 !important;
    }

    .danger {
        color: #C62828 !important;
    }

    .warning {
        color: #2E7D32 !important;
    }

    .orange {
        color: #F9A825 !important;
    }

    .badge-square {
        position: absolute;
        top: 3px;
        right: 10px;
        font-size: 0.7rem;
        z-index: 999;
        background-color: #1E88E5;
        color: #fff;
        padding: 5px 8px;
        border-radius: 6px;
        font-weight: 600;
    }

    .card-recap-body {
        padding: 14px !important;
        line-height: 1.2rem;
    }

    .text-primary-new {
        color: #1E88E5 !important;
    }

    .text-success-new {
        color: #43A047 !important;
    }

    .text-warning-new {
        color: #FDD835 !important;
    }

    .text-info-new {
        color: #8E24AA !important;
    }

    .text-late {
        color: #E53935;
        font-weight: 500;
    }

    .imaged {
        width: 64px;
        height: 64px;
        object-fit: cover;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }

    .imaged.w48 {
        width: 64px;
        height: 64px;
        object-fit: cover;
        border-radius: 8px;
    }

    .avatar-leaderboard {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        object-fit: cover;
        cursor: pointer;
        border: 2px solid #e0e0e0;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        inset: 0;
        background-color: rgba(0, 0, 0, 0.9);
    }

    .modal-content {
        margin: auto;
        display: block;
        width: 85%;
        max-width: 750px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
    }

    .close {
        position: absolute;
        top: 20px;
        right: 40px;
        color: #fff;
        font-size: 42px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.3s;
    }

    .close:hover {
        color: #bbb;
    }

    .recap-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        border-radius: 8px;
        overflow: hidden; /* For rounded corners */
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .recap-table th,
    .recap-table td {
        padding: 10px 8px;
        text-align: center;
        vertical-align: middle;
        font-size: 14px;
    }

    .recap-table th {
        background-color: #1a73e8;
        color: #fff;
        font-weight: 600;
    }
    
    .recap-table .header-row th:first-child {
        background-color: #1e88e5; /* Biru */
    }

    .recap-table .header-row th:nth-child(2) {
        background-color: #43a047; /* Hijau */
    }

    .recap-table .header-row th:last-child {
        background-color: #FB8C00; /* Orange */
    }

    .recap-table td {
        background-color: #fff;
        border-bottom: 1px solid #eee;
    }

    .recap-table td.cell-jam-kerja {
        background-color: #e8f0fe;
        color: #1e88e5;
        font-weight: bold;
    }

    .recap-table td.cell-jam-hadir {
        background-color: #e8f8e8;
        color: #43a047;
        font-weight: bold;
    }

    .recap-table td.cell-kehadiran {
        background-color: #fff6e8;
        color: #ff8c00;
        font-weight: bold;
    }


    .rekap-table-bulanan {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 8px; /* Mengurangi jarak antar baris */
        font-size: 13px;
    }

    .rekap-table-bulanan th,
    .rekap-table-bulanan td {
        padding: 8px;
        text-align: center;
        border: none;
        vertical-align: middle;
        background-color: #fff;
    }

    .rekap-table-bulanan thead th {
        background-color: #1e88e5;
        color: white;
        font-weight: 600;
        border-bottom: 2px solid #ddd;
    }

    .rekap-table-bulanan tbody tr {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        border-radius: 8px;
        transition: transform 0.2s;
    }

    .rekap-table-bulanan tbody tr:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .rekap-table-bulanan tbody td:first-child {
        border-top-left-radius: 8px;
        border-bottom-left-radius: 8px;
    }

    .rekap-table-bulanan tbody td:last-child {
        border-top-right-radius: 8px;
        border-bottom-right-radius: 8px;
    }

    .rekap-table-bulanan .text-success {
        color: #28a745 !important;
    }

    .rekap-table-bulanan .text-danger {
        color: #dc3545 !important;
        font-weight: 600;
    }

    .rekap-table-bulanan .text-warning {
        color: #ffc107 !important;
    }
    .rekap-table-bulanan .text-info {
        color: #17a2b8 !important;
    }

    .presence-item {
        display: flex;
        align-items: center;
        padding: 12px;
    }

    .presence-item .iconpresence {
        margin-right: 12px;
    }

    .presence-item .iconpresence img,
    .presence-item .iconpresence ion-icon {
        width: 70px;
        height: 70px;
        object-fit: cover;
        border-radius: 6px;
    }

    .presence-item .presencedetail {
        line-height: 1.2;
        text-align: left;
    }

    .presence-item .presencedetail h4 {
        font-size: 1.1rem;
        margin: 0;
        font-weight: 600;
    }

    .presence-item .presencedetail span {
        font-size: 0.9rem;
    }

    .todaypresence .card-body {
        padding: 0;
    }
        .close:hover {
        color: #bbb;
    }
    
    /* Container untuk konten di dalam modal */
    .modal-content-container {
        margin: auto;
        display: block;
        width: 90%; 
        max-width: 600px; 
        position: relative;
        border-radius: 8px; 
        background-color: #fefefe; 
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        animation-name: zoom; 
        animation-duration: 0.3s;
    }

    /* Styling untuk Gambar */
    .modal-image {
        width: 100%;
        height: auto;
        display: block;
        border-radius: 0 0 8px 8px; 
    }

    /* Styling Header */
    .modal-header {
        padding: 10px 15px;
        background-color: #f7f7f7;
        border-bottom: 1px solid #eee;
        border-radius: 8px 8px 0 0;
    }

    .modal-header h4 {
        margin: 0;
        font-size: 1.1rem;
        color: #333;
        text-align: center;
    }

    /* Perubahan untuk Tombol Tutup Agar Pindah ke Luar Kotak Konten */
    .modal + .close { 
        position: absolute;
        top: 20px; /* Atur posisi agar di luar kontainer, di atas overlay */
        right: 40px;
        color: #fff; 
        font-size: 42px;
        font-weight: bold;
        cursor: pointer;
        z-index: 1001;
    }

    /* Animasi Zoom (untuk tampilan lebih baik) */
    @keyframes zoom {
        from {transform:scale(0)} 
        to {transform:scale(1)}
    }
    .logo-header {
    position: absolute;
    right: 40px; /* Jarak dari kanan, di sebelah tombol logout */
    /* UBAH UKURAN: Menyesuaikan agar sama dengan tinggi foto profil (60px) */
    width: 75px; /* Lebar 60px */
    height: 75px; /* Tinggi 60px */
    /* UBAH POSISI: Sesuaikan posisi 'top' agar sejajar dengan foto profil yang berjarak 15px dari atas */
    top: 36px; 
    z-index: 10;
    cursor: pointer;
}
    
</style>

<div class="section" id="user-section">
    {{-- MODIFIKASI: Menambahkan Logo --}}
    <img src="{{ asset('assets/img/logopresensi.png') }}" alt="Logo Presensi" class="logo-header">
    
    <a href="/proseslogout" class="logout">
        <ion-icon name="exit-outline"></ion-icon>
    </a>
    <div id="user-detail">
        <div class="avatar">
            {{-- Menggunakan Auth::guard('karyawan')->user() untuk data karyawan --}}
            @if (!empty(Auth::guard('karyawan')->user()->foto))
                @php
                    $path = Storage::url('uploads/karyawan/' . Auth::guard('karyawan')->user()->foto);
                @endphp
                <img src="{{ url($path) }}" alt="avatar" class="imaged w64" style="height:60px">
            @else
                {{-- Asumsi Anda memiliki fallback image --}}
                <img src="{{ asset('assets/img/sample/avatar/avatar1.jpg') }}" alt="avatar" class="imaged w64 rounded">
            @endif
        </div>
        <div id="user-info">
            <h3 id="user-name">{{ Auth::guard('karyawan')->user()->nama_lengkap }}</h3>
            <span id="user-role">{{ Auth::guard('karyawan')->user()->jabatan }}</span>
            <span id="user-role">({{ $cabang->nama_cabang ?? 'N/A' }})</span>
            <p style="margin-top: 15px">
                <span id="user-role">({{ $departemen->nama_dept ?? 'N/A' }})</span>
            </p>
        </div>
    </div>
</div>

<div class="section" id="menu-section">
    <div class="card">
        <div class="card-body text-center">
            <div class="list-menu">
                <div class="item-menu text-center">
                    <div class="menu-icon">
                        <a href="/editprofile" class="green" style="font-size: 40px;">
                            <ion-icon name="person-sharp"></ion-icon>
                        </a>
                    </div>
                    <div class="menu-name">
                        <span class="text-center">Profil</span>
                    </div>
                </div>
                <div class="item-menu text-center">
                    <div class="menu-icon">
                        <a href="/presensi/izin" class="danger" style="font-size: 40px;">
                            <ion-icon name="calendar-number"></ion-icon>
                        </a>
                    </div>
                    <div class="menu-name">
                        <span class="text-center">Izin/Cuti</span>
                    </div>
                </div>
                <div class="item-menu text-center">
                    <div class="menu-icon">
                        <a href="/presensi/histori" class="warning" style="font-size: 40px;">
                            <ion-icon name="document-text"></ion-icon>
                        </a>
                    </div>
                    <div class="menu-name">
                        <span class="text-center">Histori</span>
                    </div>
                </div>
                <div class="item-menu text-center">
                    <div class="menu-icon">
                        <a href="" class="orange" style="font-size: 40px;">
                            <ion-icon name="location"></ion-icon>
                        </a>
                    </div>
                    <div class="menu-name">
                        Lokasi
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="section mt-2" id="presence-section">
    <div class="todaypresence">
        <div class="row">
            <div class="col-6">
                <div class="card gradasigreen">
                    <div class="card-body">
                        <div class="presence-item">
                            <div class="iconpresence">
                                @if ($presensihariini != null)
                                    @if ($presensihariini->foto_in != null)
                                        @php
                                            $path = Storage::url('uploads/absensi/' . $presensihariini->foto_in);
                                        @endphp
                                        <img src="{{ url($path) }}" alt="Foto Masuk" class="imaged">
                                    @else
                                        <ion-icon name="camera" style="font-size: 70px;"></ion-icon>
                                    @endif
                                @else
                                    <ion-icon name="camera" style="font-size: 70px;"></ion-icon>
                                @endif
                            </div>
                            <div class="presencedetail">
                                <h4 class="presencetitle">Masuk</h4>
                                <span>{{ $presensihariini != null ? $presensihariini->jam_in : 'Belum Absen' }}</span>
                                        @if($presensihariini != null && $presensihariini->jam_in > $jamMasukKantor)
                                {{-- Diubah: Menambahkan inline style untuk warna putih --}}
                                <span class="text-late" style="color: white !important;">(Terlambat)</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card gradasired">
                    <div class="card-body">
                        <div class="presence-item">
                            <div class="iconpresence">
                                @if ($presensihariini != null && $presensihariini->jam_out != null)
                                    @if ($presensihariini->foto_out != null)
                                        @php
                                            $path = Storage::url('uploads/absensi/' . $presensihariini->foto_out);
                                        @endphp
                                        <img src="{{ url($path) }}" alt="Foto Pulang" class="imaged">
                                    @else
                                        <ion-icon name="camera" style="font-size: 70px;"></ion-icon>
                                    @endif
                                @else
                                    <ion-icon name="camera" style="font-size: 70px;"></ion-icon>
                                @endif
                            </div>
                            <div class="presencedetail">
                                <h4 class="presencetitle">Pulang</h4>
                                <span>{{ $presensihariini != null && $presensihariini->jam_out != null ? $presensihariini->jam_out : 'Belum Absen' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="section mt-2" id="rekappresensi">
  <h3 class="text-center">Rekap Hadir Bulan Ini</h3>
        <div class="card">
            <div class="card-body">
                <table class="recap-table">
                    <thead class="header-row">
                        <tr>
                            <th>Total Jam Kerja</th>
                            <th>Total Jam Hadir</th>
                            <th> Persentase</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="cell-jam-kerja">{{ sprintf('%02d', $totalJamKerjaJam) }} Jam {{ sprintf('%02d', $totalJamKerjaMenit) }} Menit</td>
                            <td class="cell-jam-hadir">{{ sprintf('%02d', $totalJamHadirJam) }} Jam {{ sprintf('%02d', $totalJamHadirMenit) }} Menit</td>
                            <td class="cell-kehadiran">{{ number_format($persentase, 2) }} %</td>
                        </tr>
                    </tbody>
                </table>
               {{-- Card Rekap Status Harian (Versi Paling Ringkas) --}}
<h6 class="mt-3 mb-1 text-center" style="font-size: 1rem;"></h6>
<div class="row">
    
    {{-- Hadir --}}
    <div class="col-2">
        <div class="text-center p-1" style="border: 1px solid #43A047; border-radius: 5px;">
            <p class="m-0 text-success-new" style="font-size: 1.1rem; font-weight: bold; line-height: 1;">{{ $hadirBulanan }}</p>
            <small class="m-0" style="font-size: 0.65rem;">Hadir</small>
        </div>
    </div>

    {{-- Izin --}}
    <div class="col-2">
        <div class="text-center p-1" style="border: 1px solid #1E88E5; border-radius: 5px;">
            <p class="m-0 text-primary-new" style="font-size: 1.1rem; font-weight: bold; line-height: 1;">{{ $izinBulanan }}</p>
            <small class="m-0" style="font-size: 0.65rem;">Izin</small>
        </div>
    </div>

    {{-- Sakit --}}
    <div class="col-2">
        <div class="text-center p-1" style="border: 1px solid #FDD835; border-radius: 5px;">
            <p class="m-0 text-warning-new" style="font-size: 1.1rem; font-weight: bold; line-height: 1;">{{ $sakitBulanan }}</p>
            <small class="m-0" style="font-size: 0.65rem;">Sakit</small>
        </div>
    </div>

    {{-- Cuti --}}
    <div class="col-2">
        <div class="text-center p-1" style="border: 1px solid #8E24AA; border-radius: 5px;">
            <p class="m-0 text-info-new" style="font-size: 1.1rem; font-weight: bold; line-height: 1;">{{ $cutiBulanan }}</p>
            <small class="m-0" style="font-size: 0.65rem;">Cuti</small>
        </div>
    </div>
    
    {{-- Dinas Luar (D) --}}
    <div class="col-2">
        <div class="text-center p-1" style="border: 1px solid #4CAF50; border-radius: 5px;">
            <p class="m-0 text-success" style="font-size: 1.1rem; font-weight: bold; line-height: 1;">{{ $dinasLuarBulanan }}</p>
            <small class="m-0" style="font-size: 0.65rem;">D. Luar</small>
        </div>
    </div>

    {{-- Alpa --}}
    <div class="col-2">
        <div class="text-center p-1" style="border: 1px solid #E53935; border-radius: 5px;">
            <p class="m-0 text-danger" style="font-size: 1.1rem; font-weight: bold; line-height: 1;">{{ $alpaBulanan }}</p>
            <small class="m-0" style="font-size: 0.65rem;">Alpa</small>
        </div>
    </div>

</div>
                
            </div>
        </div>
    </div>

    <div class="presencetab mt-2">
        <div class="tab-pane fade show active" id="pilled" role="tabpanel">
            <ul class="nav nav-tabs style1" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#home" role="tab">
                        Bulan Ini
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#profile" role="tab">
                        Leaderboard
                    </a>
                </li>
            </ul>
        </div>
        <div class="tab-content mt-2" style="margin-bottom:100px;">
            <div class="tab-pane fade show active" id="home" role="tabpanel">
                <table class="table rekap-table-bulanan">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Hari</th>
                            <th>Jam Masuk</th>
                            <th>Jam Pulang</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $hariSaatIni = $tanggalAkhirLoop->copy();
                        @endphp
                        
                        {{-- Looping dari tanggal hari ini mundur ke tanggal 1 --}}
                        @while ($hariSaatIni->gte($tanggalAwalLoop))
                            @php
                                $tanggal = $hariSaatIni->format('Y-m-d');
                                $presensiHariIni = $tempPresensi->where('tgl_presensi', $tanggal)->first();
                                
                                $liburData = $tempHarilibur->where('tanggal_libur', $tanggal)->first();
                                $isHoliday = !is_null($liburData);
                                $isWeekend = $hariSaatIni->isSunday();
                                
                                $jamMasuk = '-';
                                $jamPulang = '-';
                                $keterangan = '';
                                
                                if ($isWeekend) {
                                    $keterangan = 'Libur';
                                } elseif ($isHoliday) {
                                    $keterangan = $liburData->keterangan ?? 'Libur Nasional';
                                } elseif ($presensiHariIni) {
                                    $jamMasuk = $presensiHariIni->jam_in ?? '-';
                                    $jamPulang = $presensiHariIni->jam_out ?? '-';
                                    
                                    if ($presensiHariIni->status == 'h') {
                                        if ($presensiHariIni->jam_in > $jamMasukKantor) {
                                            $keterangan = 'Terlambat';
                                        } else {
                                            $keterangan = 'Tepat Waktu';
                                        }
                                        
                                        if ($presensiHariIni->jam_in && !$presensiHariIni->jam_out && $hariSaatIni->isToday()) {
                                            $keterangan = 'Belum Absen Pulang';
                                        } elseif ($presensiHariIni->jam_in && !$presensiHariIni->jam_out && !$hariSaatIni->isToday()) {
                                            $keterangan = 'Lupa Absen Pulang'; // Mengganti dengan Lupa untuk hari lampau
                                        }
                                        
                                    } elseif ($presensiHariIni->status == 'i') {
                                        $keterangan = 'Izin';
                                    } elseif ($presensiHariIni->status == 's') {
                                        $keterangan = 'Sakit';
                                    } elseif ($presensiHariIni->status == 'c') {
                                        $keterangan = 'Cuti';
                                    } elseif ($presensiHariIni->status == 'd') { // BARU: Dinas Luar
                                        $keterangan = 'Dinas Luar';
                                    }
                                } else {
                                    if (!$isWeekend && !$isHoliday) {
                                        $keterangan = 'Alpa';
                                    }
                                }
                            @endphp
                            <tr class="{{ $isWeekend || $isHoliday ? 'table-secondary' : '' }}">
                                <td>{{ $hariSaatIni->format('d-M-y') }}</td>
                                <td>{{ $hariSaatIni->locale('id')->isoFormat('dddd') }}</td>
                                <td>{{ $jamMasuk }}</td>
                                <td>{{ $jamPulang }}</td>
                                <td>
                                    <span class="badge {{
                                        ($keterangan == 'Terlambat' || $keterangan == 'Alpa' || str_contains($keterangan, 'Lupa Absen Pulang')) ? 'text-danger' :
                                        (($keterangan == 'Izin' || $keterangan == 'Sakit' || $keterangan == 'Cuti' || $keterangan == 'Belum Absen Pulang') ? 'text-warning' : 
                                        ($keterangan == 'Dinas Luar' ? 'text-success' : // Dinas Luar dianggap OK
                                        (($isWeekend || $isHoliday) ? 'text-info' : 'text-success')))
                                    }}">
                                        {{ $keterangan }}
                                    </span>
                                </td>
                            </tr>
                            @php
                                $hariSaatIni->subDay();
                            @endphp
                        @endwhile
                    </tbody>
                </table>
            </div>

            <div class="tab-pane fade" id="profile" role="tabpanel">
    {{-- Memastikan $leaderboard ada dan merupakan array --}}
    @if(isset($leaderboard) && is_array($leaderboard) || isset($leaderboard) && $leaderboard instanceof \Illuminate\Support\Collection)
    <ul class="listview image-listview">
        @foreach ($leaderboard as $d)
            <li>
                <div class="item">
                    @php
                        $fotoInPath = !empty($d->foto_in) ? Storage::url('uploads/absensi/' . $d->foto_in) : asset('assets/img/sample/avatar/avatar1.jpg');
                        $fotoKaryawanPath = !empty($d->foto) ? Storage::url('uploads/karyawan/' . $d->foto) : asset('assets/img/sample/avatar/avatar1.jpg');
                        
                        // Tentukan status kehadiran untuk Leaderboard
                        $statusPresensi = $d->status ?? 'h'; // Asumsi default 'h' jika tidak ada status
                        $keteranganLeaderboard = '';
                        $classBadge = 'bg-secondary';

                        if ($statusPresensi == 'i') {
                            $keteranganLeaderboard = 'Izin';
                            $classBadge = 'bg-info'; 
                        } elseif ($statusPresensi == 's') {
                            $keteranganLeaderboard = 'Sakit';
                            $classBadge = 'bg-warning';
                        } elseif ($statusPresensi == 'c') {
                            $keteranganLeaderboard = 'Cuti';
                            $classBadge = 'bg-primary';
                        } elseif ($statusPresensi == 'd') { // BARU: Dinas Luar
                            $keteranganLeaderboard = 'Dinas Luar';
                            $classBadge = 'bg-success';
                        } else {
                            // Status Hadir ('h')
                            $keteranganLeaderboard = $d->jam_in ?? 'Belum Absen';
                            
                            // Logika untuk Terlambat/Tepat Waktu
                            if ($d->jam_in) {
                                $classBadge = $d->jam_in < $jamMasukKantor ? 'bg-success' : 'bg-danger';
                            } else {
                                $classBadge = 'bg-secondary';
                            }
                        }
                    @endphp
                    <img src="{{ url($fotoKaryawanPath) }}" alt="image" class="image avatar-leaderboard" data-fotoin="{{ url($fotoInPath) }}">
                    <div class="in">
                        <div>
                            <b>{{ $d->nama_lengkap }}</b><br>
                            <small class="text-muted">{{ $d->jabatan }}</small>
                        </div>
                        
                        {{-- Ganti bagian ini dengan logika yang baru --}}
                        <span class="badge {{ $classBadge }}">
                            {{ $keteranganLeaderboard }}
                        </span>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
    @else
        <p class="text-center mt-4">Data Leaderboard tidak tersedia.</p>
    @endif
</div>
        </div>
    </div>
</div>

{{-- Modal untuk Tampilkan Foto Masuk Leaderboard (Struktur Diperbarui) --}}
<div id="myModal" class="modal">
    
    {{-- Container untuk konten modal --}}
    <div class="modal-content-container">
        {{-- Tombol Tutup --}}
        <span class="close">&times;</span> 

        {{-- Judul atau keterangan foto --}}
        <div class="modal-header">
            <h4>Foto Presensi Masuk</h4>
        </div>
        
        {{-- Tempat Gambar --}}
        <img class="modal-image" id="img01" alt="Foto Presensi Masuk">
    </div>

</div>

@endsection

@push('myscript')
{{-- (Kode Javascript tetap sama seperti milik Anda) --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById("myModal");
        var modalImg = document.getElementById("img01");
        var closeBtn = document.getElementsByClassName("close")[0];

        var images = document.querySelectorAll('.avatar-leaderboard');
        images.forEach(function(img) {
            img.onclick = function() {
                var fotoInUrl = this.getAttribute('data-fotoin');
                
                // Cek apakah URL mengarah ke foto absensi
                if (fotoInUrl && fotoInUrl.includes('uploads/absensi')) { 
                    modal.style.display = "block"; 
                    modalImg.src = fotoInUrl;
                } else {
                    // MODIFIKASI: Menghilangkan alert() dan menggantinya dengan console.log
                    // Di sisi pengguna, tidak ada yang akan muncul.
                    console.log('Foto presensi masuk tidak tersedia. Tidak ada tindakan pop-up.');
                }
            }
        });

        closeBtn.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    });
</script>
@endpush