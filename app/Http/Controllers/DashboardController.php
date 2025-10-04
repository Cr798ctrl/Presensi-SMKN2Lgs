<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Menampilkan dashboard untuk karyawan.
     */
    public function index()
    {
        // Mendapatkan tanggal dan bulan/tahun saat ini
        $hariini = date("Y-m-d");
        $bulanini = date("m") * 1; 
        $tahunini = date("Y"); 

        // Mendapatkan data user yang sedang login
        $nik = Auth::guard('karyawan')->user()->nik;
        $kode_cabang = Auth::guard('karyawan')->user()->kode_cabang;
        $kode_dept = Auth::guard('karyawan')->user()->kode_dept;
        
        // Cek data presensi hari ini untuk user yang sedang login
        $presensihariini = DB::table('presensi')->where('nik', $nik)->where('tgl_presensi', $hariini)->first();
        
        // Mengambil histori presensi bulan ini
        $historibulanini = DB::table('presensi')
            ->select('presensi.*', 'keterangan', 'jam_kerja.*', 'doc_sid', 'nama_cuti')
            ->leftJoin('jam_kerja', 'presensi.kode_jam_kerja', '=', 'jam_kerja.kode_jam_kerja')
            ->leftJoin('pengajuan_izin', 'presensi.kode_izin', '=', 'pengajuan_izin.kode_izin')
            ->leftJoin('master_cuti', 'pengajuan_izin.kode_cuti', '=', 'master_cuti.kode_cuti')
            ->where('presensi.nik', $nik)
            ->whereRaw('MONTH(tgl_presensi)="' . $bulanini . '"')
            ->whereRaw('YEAR(tgl_presensi)="' . $tahunini . '"')
            ->orderBy('tgl_presensi', 'desc')
            ->get();

        // Menghitung rekap presensi (hadir, izin, sakit, cuti, terlambat) bulan ini
        $rekappresensi = DB::table('presensi')
            ->selectRaw('
                SUM(IF(status="h",1,0)) as jmlhadir,
                SUM(IF(status="i",1,0)) as jmlizin,
                SUM(IF(status="s",1,0)) as jmlsakit,
                SUM(IF(status="c",1,0)) as jmlcuti,
                SUM(IF(jam_in > jam_masuk ,1,0)) as jmlterlambat
            ')
            ->leftJoin('jam_kerja', 'presensi.kode_jam_kerja', '=', 'jam_kerja.kode_jam_kerja')
            ->where('nik', $nik)
            ->whereRaw('MONTH(tgl_presensi)="' . $bulanini . '"')
            ->whereRaw('YEAR(tgl_presensi)="' . $tahunini . '"')
            ->first();

        // Mengambil data leaderboard (urutan presensi tercepat) hari ini
        $leaderboard = DB::table('presensi')
            ->join('karyawan', 'presensi.nik', '=', 'karyawan.nik')
            ->where('tgl_presensi', $hariini)
            ->orderBy('jam_in')
            ->get();
            
        // Daftar nama bulan untuk tampilan
        $namabulan = ["", "Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
        
        // Mengambil data hari libur bulan dan tahun ini
        $harilibur = DB::table('harilibur')
            ->whereRaw('MONTH(tanggal_libur)="' . $bulanini . '"')
            ->whereRaw('YEAR(tanggal_libur)="' . $tahunini . '"')
            ->get();

        // Mengambil data cabang dan departemen
        $cabang = DB::table('cabang')->where('kode_cabang', $kode_cabang)->first();
        $departemen = DB::table('departemen')->where('kode_dept', $kode_dept)->first();

        // Mengirimkan semua data ke view dashboard
        return view('dashboard.dashboard', compact('presensihariini', 'historibulanini', 'namabulan', 'bulanini', 'tahunini', 'rekappresensi', 'leaderboard', 'cabang', 'departemen', 'harilibur'));
    }

    /**
     * Menampilkan dashboard untuk admin.
     */
    public function dashboardadmin()
    {
        $hariini = date("Y-m-d");

        // Menghitung rekap presensi hari ini untuk semua karyawan
        $rekappresensi = DB::table('presensi')
            ->selectRaw('
                SUM(IF(status="h",1,0)) as jmlhadir,
                SUM(IF(status="i",1,0)) as jmlizin,
                SUM(IF(status="s",1,0)) as jmlsakit,
                SUM(IF(status="c",1,0)) as jmlcuti,
                SUM(IF(jam_in > jam_masuk ,1,0)) as jmlterlambat
            ')
            ->leftJoin('jam_kerja', 'presensi.kode_jam_kerja', '=', 'jam_kerja.kode_jam_kerja')
            ->where('tgl_presensi', $hariini)
            ->first();

        return view('dashboard.dashboardadmin', compact('rekappresensi'));
    }
}
