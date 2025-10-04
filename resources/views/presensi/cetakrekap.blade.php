<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Cetak Rekap Laporan</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/7.0.0/normalize.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/paper-css/0.4.1/paper.css">

    <style>
        @page {
            /* KONFIGURASI F4 LANDSCAPE */
            size: F4 landscape;
            margin-top: 0.5cm;
            margin-left: 1cm;
            margin-right: 1cm;
            margin-bottom: 0.5cm;
        }

        #title {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 16px;
            font-weight: bold;
        }

        .tabeldatakaryawan {
            margin-top: 20px;
        }

        .tabeldatakaryawan tr td {
            padding: 3px;
        }

        .tabelpresensi {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        .tabelpresensi tr th,
        .tabelpresensi tr td {
            border: 1px solid #131212;
            padding: 5px;
            font-size: 10px;
        }

        .tabelpresensi tr th {
            background-color: #dbdbdb;
        }

        /* 💡 Style untuk Keterangan agar rapi */
        .tabel-keterangan {
            width: 100%;
            font-size: 10px;
            margin-top: 5px;
        }
        .tabel-keterangan td {
            padding: 0;
            vertical-align: top;
        }
        .tabel-keterangan ul {
            list-style: none;
            padding-left: 0;
            margin-top: 0;
            margin-bottom: 0;
        }
        .keterangan-header {
            font-size: 14px;
            margin-bottom: 5px;
        }
    </style>
</head>

<body class="F4 landscape">
    @php
        use Carbon\Carbon;
        setlocale(LC_TIME, 'id_ID.utf8');

        function formatTanggalIndonesia($tanggal) {
            return strftime("%d %B %Y", strtotime($tanggal));
        }

        // Fungsi untuk memformat total jam (desimal) menjadi "XX jam XX menit"
        $formatDurasi = function ($totalJamDesimal) {
            $totalMenit = round($totalJamDesimal * 60);
            $jam = floor($totalMenit / 60);
            $menit = $totalMenit % 60;
            return sprintf('%02d jam %02d menit', $jam, $menit);
        };

        $today = date('Y-m-d');
        // Jam Kerja Wajib Per Hari
        $jamKerjaPerHari = [
            1 => 7,   // Senin
            2 => 7,   // Selasa
            3 => 7,   // Rabu
            4 => 6.5, // Kamis
            5 => 4,   // Jumat
            6 => 6,   // Sabtu
            0 => 0,   // Minggu
        ];
    @endphp

    <section class="sheet padding-10mm">
        <table style="width: 100%">
            <tr>
                <td style="width: 30px">
                    <img src="{{ asset('assets/img/logopresensi.png') }}" width="70" height="80" alt="">
                </td>
                <td>
                    <span id="title">
                        REKAP PRESENSI PEGAWAI<br>
                        PERIODE {{ strtoupper($namabulan[$bulan]) }} {{ $tahun }}<br>
                        SMK NEGERI 2 LANGSA<br>
                    </span>
                    <span><i>Jl. Jenderal Ahmad Yani, Paya Bujok Seuleumak, Kec. Langsa Baro, Kota Langsa, Aceh 24415</i></span>
                </td>
            </tr>
        </table>
        <table class="tabelpresensi">
            <thead>
                <tr>
                    <th rowspan="2">NIP/NPPPK</th>
                    <th rowspan="2">Nama Pegawai</th>
                    <th colspan="{{ $jmlhari }}">Bulan {{ $namabulan[$bulan] }} {{ $tahun }}</th>
                    <th rowspan="2">H</th>
                    <th rowspan="2">I</th>
                    <th rowspan="2">S</th>
                    <th rowspan="2">C</th>
                    <th rowspan="2">D</th>
                    <th rowspan="2">A</th> 
                    <th rowspan="2">Total Jam Hadir</th>
                    {{-- 💡 PERBAIKAN HEADER: Menambahkan Total Jam Kerja --}}
                    <th rowspan="2">Total Jam Kerja</th> 
                    <th rowspan="2">% Jam Hadir</th>
                </tr>
                <tr>
                    @foreach ($rangetanggal as $d)
                        @if ($d != null)
                            <th>{{ date('d', strtotime($d)) }}</th>
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rekap as $r)
                    @php
                        // 💡 PERBAIKAN INIALISASI: Menambahkan $dinasluar
                        $hadir = $izin = $sakit = $cuti = $alpa = $dinasluar = 0; 
                        $totalJamHadir = 0;
                        $totalJamSeharusnya = 0;
                        $tempHarilibur = collect($harilibur);
                        
                        $jamHadirMingguIni = 0;
                        $jamSeharusnyaMingguIni = 0;
                        $isLastDayOfMonth = false;
                    @endphp
                    <tr>
                        <td>{{ $r->nik }}</td>
                        <td>{{ $r->nama_lengkap }}</td>
                        @for ($i = 1; $i <= $jmlhari; $i++)
                            @php
                                $tanggal = date('Y-m-d', strtotime($rangetanggal[$i - 1]));
                                $currentDayCarbon = Carbon::parse($tanggal);
                                $dayOfWeek = $currentDayCarbon->dayOfWeek; // 0=Minggu, 1=Senin, ..., 6=Sabtu
                                
                                $isWeekend = $currentDayCarbon->isSunday();
                                $isHoliday = $tempHarilibur->where('tanggal_libur', $tanggal)->first();
                                $isEndOfWorkWeek = $currentDayCarbon->isSaturday(); // Sabtulah penutup pekan kerja

                                // Cek jika ini hari terakhir dari data yang direkap
                                $isLastDayOfData = ($i == $jmlhari);

                                $bgColor = 'white';
                                $statusDisplay = '';
                                $jamSeharusnyaHariIni = 0;
                                $jamHadirHariIni = 0;
                                $tambahJamSeharusnya = false;
                            @endphp
                            
                            {{-- LOGIKA UTAMA PERHITUNGAN DAN CAPPING --}}
                            @php
                                if ($isWeekend || $isHoliday) {
                                    // Warna Hari Minggu/Libur Nasional
                                    $bgColor = $isWeekend ? '#ea4b16' : '#99ff99'; 
                                    $statusDisplay = $isWeekend ? 'M' : 'L'; // Tambah M/L untuk keterangan libur
                                    
                                    // Capping Cepat untuk Hari Libur/Minggu
                                    if ($jamSeharusnyaMingguIni > 0) {
                                        $totalJamHadir += min($jamHadirMingguIni, $jamSeharusnyaMingguIni);
                                        $totalJamSeharusnya += $jamSeharusnyaMingguIni;
                                        $jamHadirMingguIni = 0;
                                        $jamSeharusnyaMingguIni = 0;
                                    }

                                } elseif ($tanggal > $today) {
                                    $statusDisplay = ''; // Hari belum terjadi
                                } else {
                                    // Ini adalah hari kerja (Senin-Sabtu)
                                    $jamSeharusnyaHariIni = $jamKerjaPerHari[$dayOfWeek];
                                    
                                    $dataAbsensi = isset($r->{"tgl_" . $i}) ? explode("|", $r->{"tgl_" . $i}) : [];
                                    $status = strtolower(trim($dataAbsensi[2] ?? ''));

                                    if ($status == 'h') {
                                        $statusDisplay = 'H';
                                        $hadir++;
                                        $tambahJamSeharusnya = true;
                                        
                                        $jam_in = trim($dataAbsensi[0] ?? '');
                                        $jam_out = trim($dataAbsensi[1] ?? '');

                                        $isValidJamIn = !empty($jam_in) && !in_array($jam_in, ['NA', '00:00:00']);
                                        $isValidJamOut = !empty($jam_out) && !in_array($jam_out, ['NA', '00:00:00']);

                                        if ($isValidJamIn && $isValidJamOut) {
                                            $jamIn = Carbon::parse($tanggal . ' ' . $jam_in);
                                            $jamOut = Carbon::parse($tanggal . ' ' . $jam_out);
                                            $durasiMenit = $jamOut->diffInMinutes($jamIn);

                                            // Logika Potongan Jam Istirahat (contoh untuk Jumat 12:00-14:00)
                                            if ($dayOfWeek == Carbon::FRIDAY) {
                                                $breakStart = Carbon::parse($tanggal . ' 12:00:00');
                                                $breakEnd = Carbon::parse($tanggal . ' 14:00:00');
                                                if ($jamIn->lte($breakStart) && $jamOut->gte($breakEnd)) {
                                                    $durasiMenit -= 120; // Potong 2 jam istirahat
                                                }
                                            }
                                            $jamHadirHariIni = $durasiMenit / 60;
                                            $bgColor = 'white';
                                        } elseif ($isValidJamIn && !$isValidJamOut) {
                                            // Lupa Absen Pulang (Hadir 50% dari Jam Wajib Hari Itu)
                                            $jamHadirHariIni = ($jamKerjaPerHari[$dayOfWeek] / 2);
                                            $bgColor = '#fffacd';
                                        } else {
                                            // Tidak Absen In/Out (Dianggap Alpa 100%)
                                            $statusDisplay = 'A';
                                            $alpa++;
                                            $tambahJamSeharusnya = true;
                                            $bgColor = '#ff000030';
                                        }
                                    } elseif ($status == 'i') {
                                        $statusDisplay = 'I';
                                        $izin++;
                                        $tambahJamSeharusnya = true;
                                        $jamHadirHariIni = $jamKerjaPerHari[$dayOfWeek]; // Dihitung Hadir 100%
                                        $bgColor = '#b3e0ff';
                                    } elseif ($status == 's') {
                                        $statusDisplay = 'S';
                                        $sakit++;
                                        $tambahJamSeharusnya = true;
                                        $jamHadirHariIni = $jamKerjaPerHari[$dayOfWeek]; // Dihitung Hadir 100%
                                        $bgColor = '#800080';
                                    } elseif ($status == 'c') {
                                        $statusDisplay = 'C';
                                        $cuti++;
                                        $tambahJamSeharusnya = true;
                                        $jamHadirHariIni = $jamKerjaPerHari[$dayOfWeek]; // Dihitung Hadir 100%
                                        $bgColor = '#e6ccff';
                                    } elseif ($status == 'd') {
                                        // 💡 LOGIKA DINAS LUAR (D)
                                        $statusDisplay = 'D'; // Diubah dari DL menjadi D agar lebih ringkas
                                        $dinasluar++;
                                        $tambahJamSeharusnya = true;
                                        $jamHadirHariIni = $jamKerjaPerHari[$dayOfWeek]; // Dihitung hadir 100%
                                        $bgColor = '#ffe4b5'; 
                                    } else {
                                        $statusDisplay = 'A';
                                        $alpa++;
                                        $tambahJamSeharusnya = true;
                                        $bgColor = '#ff000030';
                                    }

                                    // Akumulasi Jam Mingguan
                                    $jamHadirMingguIni += $jamHadirHariIni;
                                    if ($tambahJamSeharusnya) {
                                        $jamSeharusnyaMingguIni += $jamSeharusnyaHariIni;
                                    }

                                    // Capping akhir pekan kerja (Sabtu)
                                    if ($isEndOfWorkWeek) {
                                        $totalJamHadir += min($jamHadirMingguIni, $jamSeharusnyaMingguIni);
                                        $totalJamSeharusnya += $jamSeharusnyaMingguIni;
                                        
                                        $jamHadirMingguIni = 0;
                                        $jamSeharusnyaMingguIni = 0;
                                    }
                                }
                            @endphp
                            
                            {{-- TAMPILAN KOTAK HARIAN --}}
                            <td style="text-align: center; background-color: {{ $bgColor }}">{{ $statusDisplay }}</td>
                        @endfor
                        
                        {{-- 💡 FINAL CAPPING: Tambahkan sisa jam dari pekan terakhir --}}
                        @php
                            if ($jamSeharusnyaMingguIni > 0) {
                                $totalJamHadir += min($jamHadirMingguIni, $jamSeharusnyaMingguIni);
                                $totalJamSeharusnya += $jamSeharusnyaMingguIni;
                            }

                            // Perhitungan Persentase
                            $persentase = ($totalJamSeharusnya > 0) ? ($totalJamHadir / $totalJamSeharusnya) * 100 : 0;
                            $persentase = number_format(min($persentase, 100), 2); // Batasi 100% dan 2 desimal
                        @endphp
                        
                        {{-- KOLOM RINGKASAN --}}
                        <td style="text-align: center">{{ $hadir }}</td>
                        <td style="text-align: center">{{ $izin }}</td>
                        <td style="text-align: center">{{ $sakit }}</td>
                        <td style="text-align: center">{{ $cuti }}</td>
                        <td style="text-align: center">{{ $dinasluar }}</td>
                        <td style="text-align: center">{{ $alpa }}</td>
                        <td style="text-align: center">{{ $formatDurasi($totalJamHadir) }}</td>
                        {{-- 💡 OUTPUT TOTAL JAM KERJA & % HADIR --}}
                        <td style="text-align: center">{{ $formatDurasi($totalJamSeharusnya) }}</td> 
                        <td style="text-align: center">{{ $persentase }} %</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table width="100%" style="margin-top: 20px; table-layout: fixed;" class="tabel-bawah">
            <tr>
                {{-- KETERANGAN (Kolom Kiri) --}}
                <td style="width: 50%; vertical-align: top; padding-right: 15px;">
                    <h4 class="keterangan-header">Keterangan Status Kehadiran:</h4>
                    
                    {{-- 💡 PERBAIKAN TAMPILAN KETERANGAN DENGAN TABLE --}}
                    <table class="tabel-keterangan">
                        <tr style="vertical-align: top;">
                            <td style="width: 50%;">
                                <ul style="list-style: none; padding-left: 0; margin-top: 0;"> 
                                    <li><span style="background-color: #ea4b16; padding: 0 5px; border: 1px solid black;">&nbsp;</span> : Hari Minggu (M)</li> 
                                    <li><span style="background-color: #99ff99; padding: 0 5px; border: 1px solid black;">&nbsp;</span> : Hari Libur (L)</li> 
                                    <li><span style="background-color: #fffacd; padding: 0 5px; border: 1px solid black;">&nbsp;</span> : Belum Absen Pulang (Hadir 50%)</li> 
                                    <li><span style="background-color: #FFFFFF; padding: 0 5px; border: 1px solid black;">&nbsp;</span> : H - Hadir</li>
                                </ul>
                            </td>
                            <td style="width: 50%;">
                                <ul style="list-style: none; padding-left: 0; margin-top: 0;"> 
                                    <li><span style="background-color: #b3e0ff; padding: 0 5px; border: 1px solid black;">&nbsp;</span> : I - Izin</li> 
                                    <li><span style="background-color: #800080; padding: 0 5px; border: 1px solid black;">&nbsp;</span> : S - Sakit</li> 
                                    <li><span style="background-color: #e6ccff; padding: 0 5px; border: 1px solid black;">&nbsp;</span> : C - Cuti</li> 
                                    <li><span style="background-color: #ffe4b5; padding: 0 5px; border: 1px solid black;">&nbsp;</span> : D - Dinas Luar</li>
                                    <li><span style="background-color: #ff000030; padding: 0 5px; border: 1px solid black;">&nbsp;</span> : A - Alpa / Tidak Hadir</li> 
                                </ul> 
                            </td>
                        </tr>
                    </table>

                    @if (count($harilibur) > 0)
                        <h4>Keterangan Hari Libur di Bulan {{ $namabulan[$bulan] }} {{ $tahun }}:</h4>
                        <ol style="font-size: 15px; margin-top: 5px;">
                            @foreach ($harilibur as $d)
                                <li>{{ formatTanggalIndonesia($d->tanggal_libur) }} - {{ $d->keterangan }}</li>
                            @endforeach
                        </ol>
                    @endif
                </td>

                {{-- TANDA TANGAN (Kolom Kanan) --}}
                <td style="width: 50%; vertical-align: top; text-align: center;">
                    <div style="margin-top: 15px;">
                        Langsa, {{ formatTanggalIndonesia(date('Y-m-d')) }}<br>
                        Kepala Sekolah
                    </div>
                    
                    <div style="height: 80px;">
                        {{-- Jarak untuk Tanda Tangan --}}
                    </div>

                    <div>
                        <b>Ir. MUHAMMAD RIDWAN, ST., MT</b><br>
                        NIP. 197206172005041001
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-top: 20px; font-size: 11px;">
                    <br>
                    Rekapan ini sesuai dengan bukti dukung terlampir.
                </td>
            </tr>
        </table>
    </section>
</body>

</html>