<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Cetak Laporan</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/7.0.0/normalize.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/paper-css/0.4.1/paper.css">

    <style>
        @page {
            size: 210mm 330mm portrait;
            /* Kertas F4 Portrait */
            margin-top: 1cm;
        }

        #title {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 18px;
            font-weight: bold;
        }

        .tabeldatakaryawan {
            margin-top: 10px;
        }

        .tabeldatakaryawan tr td {
            padding: 2px;
        }

        .tabelpresensi {
            width: 100%;
            margin-top: 1px;
            border-collapse: collapse;
        }

        .tabelpresensi tr th,
        .tabelpresensi tr td {
            border: 1px solid #131212;
            padding: 5px;
            font-size: 14px;
        }

        .tabelpresensi tr th {
            background-color: #dbdbdb;
        }

        .foto {
            width: 35px;
            height: auto;
            object-fit: cover;
        }

        .tabelrekap {
            margin-top: 20px;
            border-collapse: collapse;
            width: 100%;
        }

        .tabelrekap tr th,
        .tabelrekap tr td {
            border: 1px solid #131212;
            padding: 5px;
            font-size: 13px;
        }
    </style>
</head>

<body>
    @php
        use Carbon\Carbon;
        use Illuminate\Support\Facades\Storage;

        if (!function_exists('getHariIndonesia')) {
            function getHariIndonesia($dayOfWeek)
            {
                $hari = [
                    0 => 'Minggu',
                    1 => 'Senin',
                    2 => 'Selasa',
                    3 => 'Rabu',
                    4 => 'Kamis',
                    5 => 'Jumat',
                    6 => 'Sabtu',
                ];
                return $hari[$dayOfWeek] ?? '';
            }
        }

        if (!function_exists('hitungTerlambat')) {
            function hitungTerlambat($jamMasuk, $jamIn)
            {
                $masuk = Carbon::parse($jamMasuk);
                $in = Carbon::parse($jamIn);
                if ($in->greaterThan($masuk)) {
                    $diff = $in->diff($masuk);
                    $output = 'Terlambat ';
                    $parts = [];
                    if ($diff->h > 0) {
                        $parts[] = $diff->h . ' j';
                    }
                    if ($diff->i > 0) {
                        $parts[] = $diff->i . ' m';
                    }
                    if ($diff->s > 0) {
                        $parts[] = $diff->s . ' d';
                    }
                    return $output . implode(', ', $parts);
                }
                return 'Tepat Waktu';
            }
        }

        // Fungsi baru untuk mengubah jam desimal menjadi format '00 jam 00 menit'
        if (!function_exists('formatJamMenit')) {
            function formatJamMenit($totalHours)
            {
                if (!is_numeric($totalHours)) {
                    return '00 jam 00 menit';
                }

                $totalHours = abs($totalHours);
                $hours = floor($totalHours);
                $minutes = round(($totalHours - $hours) * 60);

                if ($minutes >= 60) {
                    $hours += floor($minutes / 60);
                    $minutes %= 60;
                }

                return sprintf('%02d jam %02d menit', $hours, $minutes);
            }
        }

        // --- VARIABEL UNTUK PERHITUNGAN REKAPITULASI (DIPERBAIKI) ---
        $hadir = $IzinSakit = $cuti = $alpa = $dinasLuar = 0; // KOREKSI: Menghilangkan **
        $totalJamHadir = 0; // Total Jam Hadir BULANAN (setelah capping)
        $totalJamSeharusnya = 0; // Total Jam Wajib BULANAN

        // Variabel untuk perhitungan mingguan (capping)
        $jamHadirMingguIni = 0;
        $jamSeharusnyaMingguIni = 0;

        $jamKerjaPerHari = [
            1 => 7, // Senin
            2 => 7, // Selasa
            3 => 7, // Rabu
            4 => 6.5, // Kamis
            5 => 4, // Jumat
            6 => 6, // Sabtu
            0 => 0, // Minggu
        ];

        $startDate = Carbon::createFromDate($tahun, $bulan, 1);
        $currentDate = Carbon::now();
        if ($tahun == $currentDate->year && $bulan == $currentDate->month) {
            $endDate = $currentDate;
        } else {
            $endDate = $startDate->copy()->endOfMonth();
        }

        $tempPresensi = collect($presensi);
        $tempHarilibur = collect($harilibur);

        for ($i = $startDate->copy(); $i->lte($endDate); $i->addDay()) {
            $dayOfWeek = $i->dayOfWeek;
            $presensiHariIni = $tempPresensi->where('tgl_presensi', $i->format('Y-m-d'))->first();
            $liburData = $tempHarilibur->where('tanggal_libur', $i->format('Y-m-d'))->first();
            $isHoliday = !is_null($liburData);
            $isWeekend = $i->isSunday();
            
            $jamSeharusnyaHariIni = 0;
            $jamHadirHariIni = 0;
            $tambahJamSeharusnya = false; // Default false, diubah jadi true jika hari kerja terlewati atau ada status i/s/c/d

            // Tentukan hari capping: Sabtu atau Minggu/Libur (yang menutup pekan kerja sebelumnya)
            $isCappingDay = $i->isSaturday() || $i->isSunday() || $isHoliday;
            
            // --- LOGIKA HARI KERJA (Senin - Sabtu) ---
            if (!$isWeekend && !$isHoliday) {
                $jamSeharusnyaHariIni = $jamKerjaPerHari[$dayOfWeek];
                $tambahJamSeharusnya = true; // Hari kerja wajib dihitung jam seharusnya

                if ($presensiHariIni) {
                    if ($presensiHariIni->status == 'h') {
                        $hadir++;
                        // Perhitungan Jam Hadir
                        if ($presensiHariIni->jam_in && $presensiHariIni->jam_out) {
                            $jamIn = Carbon::parse($i->format('Y-m-d') . ' ' . $presensiHariIni->jam_in);
                            $jamOut = Carbon::parse($i->format('Y-m-d') . ' ' . $presensiHariIni->jam_out);
                            $durasiMenit = $jamOut->diffInMinutes($jamIn);
                            
                            // Potongan Jam Istirahat (contoh untuk Jumat 12:00-14:00)
                            if ($i->dayOfWeek == Carbon::FRIDAY) {
                                $breakStart = Carbon::parse($i->format('Y-m-d') . ' 12:00:00');
                                $breakEnd = Carbon::parse($i->format('Y-m-d') . ' 14:00:00');
                                if ($jamIn->lte($breakStart) && $jamOut->gte($breakEnd)) {
                                    $durasiMenit -= 120;
                                }
                            }
                            $jamHadirHariIni = $durasiMenit / 60;
                        } elseif ($presensiHariIni->jam_in && !$presensiHariIni->jam_out) {
                            // Lupa Absen Pulang = 50%
                            $jamHadirHariIni = ($jamKerjaPerHari[$dayOfWeek] / 2);
                        } else {
                            // Tidak Absen In/Out pada Hari Kerja (Status 'h' tapi tidak ada jam) => ALPA
                            $alpa++;
                            $hadir--; // Batalkan hitungan Hadir
                            $jamHadirHariIni = 0;
                        }
                    } elseif (in_array($presensiHariIni->status, ['i', 's'])) {
                        $IzinSakit++;
                        $jamHadirHariIni = 0;
                        // Tetap hitung jam seharusnya ($tambahJamSeharusnya = true di awal blok if)
                    } elseif ($presensiHariIni->status == 'c') {
                        $cuti++;
                        $jamHadirHariIni = 0;
                        // Tetap hitung jam seharusnya ($tambahJamSeharusnya = true di awal blok if)
                    } elseif ($presensiHariIni->status == 'd') { // LOGIKA DINAS LUAR
                        $dinasLuar++;
                        $jamHadirHariIni = $jamKerjaPerHari[$dayOfWeek]; // Dinas Luar dianggap hadir penuh
                        // Tetap hitung jam seharusnya ($tambahJamSeharusnya = true di awal blok if)
                    }
                } else {
                    // Alpa (Tidak ada data presensi pada hari kerja)
                    $alpa++;
                    $jamHadirHariIni = 0;
                    // Tetap hitung jam seharusnya ($tambahJamSeharusnya = true di awal blok if)
                }
            } else {
                // Hari Libur / Weekend
                $tambahJamSeharusnya = false;
            }
            
            // Akumulasi jam ke variabel mingguan (hanya untuk hari kerja yang terlewati dan dihitung jam wajibnya)
            $jamHadirMingguIni += $jamHadirHariIni;
            
            // Akumulasi jam seharusnya ke variabel mingguan
            if ($tambahJamSeharusnya && !$isWeekend && !$isHoliday) {
                $jamSeharusnyaMingguIni += $jamSeharusnyaHariIni;
            }


            // LOGIKA CAPPING MINGGUAN PADA HARI CAPPING
            if ($isCappingDay) {
                if ($jamSeharusnyaMingguIni > 0) {
                    // Capping: Ambil nilai terkecil antara jam hadir dan jam seharusnya untuk minggu ini.
                    $totalJamHadir += min($jamHadirMingguIni, $jamSeharusnyaMingguIni);
                    
                    // Akumulasikan total jam seharusnya ke variabel bulanan
                    $totalJamSeharusnya += $jamSeharusnyaMingguIni;
                }
                
                // Reset akumulasi mingguan
                $jamHadirMingguIni = 0;
                $jamSeharusnyaMingguIni = 0;
            }
        }
        
        // 💡 FINAL CAPPING untuk Pekan Terakhir
        if ($jamSeharusnyaMingguIni > 0) {
            $totalJamHadir += min($jamHadirMingguIni, $jamSeharusnyaMingguIni);
            $totalJamSeharusnya += $jamSeharusnyaMingguIni;
        }

        // Perhitungan Persentase
        if ($totalJamSeharusnya > 0) {
            $persentase = ($totalJamHadir / $totalJamSeharusnya) * 100;
            $persentase = min($persentase, 100);
        } else {
            $persentase = 0;
        }

        // --- FORMAT AKHIR UNTUK TAMPILAN (menggunakan fungsi yang baru dibuat) ---
        $formattedTotalJamHadir = formatJamMenit($totalJamHadir);
        $formattedTotalJamSeharusnya = formatJamMenit($totalJamSeharusnya);
            
    @endphp

    <section class="sheet padding-10mm">
        <table style="width: 100%">
            <tr>
                <td style="width: 30px">
                    <img src="{{ asset('assets/img/logopresensi.png') }}" width="70" height="83" alt="">
                </td>
                <td>
                    <span id="title">
                        LAPORAN PRESENSI PEGAWAI<br>
                        PERIODE {{ strtoupper($namabulan[$bulan]) }} {{ $tahun }}<br>
                        SMK NEGERI 2 LANGSA<br>
                    </span>
                    <span><i>Jl. Jenderal Ahmad Yani, Paya Bujok Seuleumak, Kec. Langsa Baro, Kota Langsa, Aceh 24415</i></span>
                </td>
            </tr>
        </table>

        <table class="tabeldatakaryawan">
            <tr>
                <td rowspan="4">
                    @php
                        $path = Storage::url('uploads/karyawan/' . $karyawan->foto);
                    @endphp
                    <img src="{{ url($path) }}" alt="" width="72px" height="90">
                </td>
            </tr>
            <tr>
                <td>Nama Pegawai</td>
                <td>:</td>
                <td>{{ $karyawan->nama_lengkap }}</td>
            </tr>
            <tr>
                <td>NIP/NPPPK</td>
                <td>:</td>
                <td>{{ $karyawan->nik }}</td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>:</td>
                <td>{{ $karyawan->jabatan }}</td>
            </tr>
        </table>

        <table class="tabelpresensi">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Tanggal</th>
                    <th>Hari</th>
                    <th>Jam Masuk</th>
                    <th>Foto</th>
                    <th>Jam Pulang</th>
                    <th>Foto</th>
                    <th>Status</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $counter = 1;
                    $tempPresensi = collect($presensi);
                @endphp
                @for ($i = $startDate->copy(); $i->lte($endDate); $i->addDay())
                    @php
                        $tanggal = $i->format('Y-m-d');
                        $hariIndo = getHariIndonesia($i->dayOfWeek);
                        $presensiHariIni = $tempPresensi->where('tgl_presensi', $tanggal)->first();
                        $liburData = isset($harilibur) ? $harilibur->where('tanggal_libur', $tanggal)->first() : null;
                        $isHoliday = !is_null($liburData);
                        $isWeekend = $i->isSunday();
                        $bgColor = '';
                        $status = '';
                        $keterangan = '';

                        if ($isWeekend) {
                            $bgColor = '#ffe5e5';
                            $status = 'Minggu';
                            $keterangan = 'Hari Minggu';
                        } elseif ($isHoliday) {
                            $bgColor = '#ccffcc';
                            $status = 'Libur';
                            $keterangan = $liburData->keterangan ?? 'Libur Nasional';
                        } elseif ($presensiHariIni) {
                            if ($presensiHariIni->status == 'h') {
                                if ($presensiHariIni->jam_in && $presensiHariIni->jam_out) {
                                    $status = 'Hadir';
                                    $keterangan = hitungTerlambat('07:30', $presensiHariIni->jam_in);
                                } elseif ($presensiHariIni->jam_in && !$presensiHariIni->jam_out) {
                                    $status = 'Hadir (50%)';
                                    $keterangan = 'Belum Absen Pulang';
                                } else {
                                    $status = 'Alpa';
                                    $keterangan = 'Tidak Absen Masuk/Pulang';
                                }
                            } elseif ($presensiHariIni->status == 'i') {
                                $status = 'Izin';
                                $keterangan = 'Izin';
                            } elseif ($presensiHariIni->status == 's') {
                                $status = 'Sakit';
                                $keterangan = 'Sakit';
                            } elseif ($presensiHariIni->status == 'c') {
                                $status = 'Cuti';
                                $keterangan = 'Cuti';
                            } elseif ($presensiHariIni->status == 'd') { // TAMPILAN DINAS LUAR
                                $status = 'Dinas Luar';
                                $keterangan = 'Dinas Luar';
                            }
                        } else {
                            if (!$isWeekend && !$isHoliday) {
                                $status = 'Alpa';
                                $keterangan = 'Tidak Hadir';
                            }
                        }
                    @endphp
                    <tr style="background-color: {{ $bgColor }}">
                        <td style="text-align: center">{{ $counter++ }}</td>
                        <td>{{ $i->translatedFormat('d M Y') }}</td>
                        <td style="text-align: center">{{ $hariIndo }}</td>
                        {{-- Jam Masuk dan Pulang dikosongkan jika Status I/S/C/D --}}
                        <td>{{ $isWeekend || $isHoliday || in_array($presensiHariIni->status ?? '', ['i', 's', 'c', 'd']) ? '-' : ($presensiHariIni->jam_in ?? '-') }}</td>
                        <td>
                            @if ($isWeekend || $isHoliday || in_array($status, ['Izin', 'Sakit', 'Cuti', 'Alpa', 'Dinas Luar']))
                                -
                            @elseif ($presensiHariIni && $presensiHariIni->foto_in)
                                <img src="{{ url(Storage::url('uploads/absensi/' . $presensiHariIni->foto_in)) }}" alt="" class="foto">
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $isWeekend || $isHoliday || in_array($presensiHariIni->status ?? '', ['i', 's', 'c', 'd']) ? '-' : ($presensiHariIni->jam_out ?? '-') }}</td>
                        <td>
                            @if ($isWeekend || $isHoliday || in_array($status, ['Izin', 'Sakit', 'Cuti', 'Alpa', 'Dinas Luar']))
                                -
                            @elseif ($presensiHariIni && $presensiHariIni->foto_out)
                                <img src="{{ url(Storage::url('uploads/absensi/' . $presensiHariIni->foto_out)) }}" alt="" class="foto">
                            @else
                                -
                            @endif
                        </td>
                        <td style="text-align: center">{{ $status }}</td>
                        <td>{{ $keterangan }}</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <h4 style="margin-top:20px">Rekapitulasi Kehadiran:</h4>
        <table class="tabelrekap">
            <tr>
                <th>Hadir</th>
                <th>Izin/Sakit</th>
                <th>Cuti</th>
                <th>Dinas Luar</th>
                <th>Alpa</th>
                <th>Total Jam Hadir</th>
                <th>Total Jam Kerja</th>
                <th>% Jam hadir</th>
            </tr>
            <tr>
                <td style="text-align:center">{{ $hadir }}</td>
                <td style="text-align:center">{{ $IzinSakit }}</td>
                <td style="text-align:center">{{ $cuti }}</td>
                <td style="text-align:center">{{ $dinasLuar }}</td>
                <td style="text-align:center">{{ $alpa }}</td>
                <td style="text-align:center">{{ $formattedTotalJamHadir }}</td>
                <td style="text-align:center">{{ $formattedTotalJamSeharusnya }}</td>
                <td style="text-align:center">{{ number_format($persentase, 2) }} %</td>
            </tr>
        </table>

        <table width="100%" style="margin-top:40px">
            <tr>
                <td style="width: 50%; text-align: center">
                    Mengetahui,<br>
                    Kepala Sekolah<br><br><br><br>
                    <b>Ir. MUHAMMAD RIDWAN, ST., MT</b><br>
                    NIP. 197206172005041001
                </td>
                <td style="width: 50%; text-align: center">
                    Langsa, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}<br>
                    Pegawai<br><br><br><br>
                    <b>{{ $karyawan->nama_lengkap }}</b><br>
                    NIP/NPPPK. {{ $karyawan->nik }}
                </td>
            </tr>
        </table>
    </section>
</body>

</html>