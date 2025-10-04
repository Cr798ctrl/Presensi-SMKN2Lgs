@extends('layouts.presensi')
@section('header')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0-beta/css/materialize.min.css">
<style>
    .datepicker-modal {
        max-height: 430px !important;
    }

    .datepicker-date-display {
        background-color: #0f3a7e !important;
    }

    #keterangan {
        height: 5rem !important;
    }

</style>
<!-- App Header -->
<div class="appHeader bg-primary text-light">
    <div class="left">
        <a href="javascript:;" class="headerButton goBack">
            <ion-icon name="chevron-back-outline"></ion-icon>
        </a>
    </div>
    <div class="pageTitle">Form Dinas Luar</div>
    <div class="right"></div>
</div>
<!-- * App Header -->
@endsection
@section('content')
<div class="row" style="margin-top:70px">
    <div class="col">
       <form method="POST" action="/izindinas/store" id="frmIzin" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <input type="text" id="tgl_izin_dari" autocomplete="off" name="tgl_izin_dari" class="form-control datepicker" placeholder="Dari">
            </div>
            <div class="form-group">
                <input type="text" id="tgl_izin_sampai" autocomplete="off" name="tgl_izin_sampai" class="form-control datepicker" placeholder="Sampai">
            </div>
            <div class="form-group">
                <input type="text" id="jml_hari" name="jml_hari" class="form-control" autocomplete="off" placeholder="Jumlah Hari" readonly>
            </div>
            <div class="custom-file-upload" id="fileUpload1" style="height: 100px !important">
    <input type="file" 
           name="sid" 
           id="fileuploadInput" 
           accept=".pdf" 
           capture="false">
    <label for="fileuploadInput">
        <span>
            <strong>
                <ion-icon name="cloud-upload-outline" role="img" class="md hydrated" aria-label="cloud upload outline"></ion-icon>
                <i>Tap to Upload Dokumen</i>
            </strong>
        </span>
    </label>
</div>

            <div class="form-group">
                <input type="text" id="keterangan" name="keterangan" class="form-control" autocomplete="off" placeholder="Keterangan">
            </div>
            <div class="form-group">
                <button class="btn btn-primary w-100">Kirim</button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('myscript')
<script>
    $(document).ready(function() {
        // Inisialisasi datepicker Materialize
        $(".datepicker").datepicker({
            format: "yyyy-mm-dd",
            minDate: new Date() // tidak bisa pilih tanggal sebelum hari ini
        });

        // Hitung jumlah hari otomatis
        function loadjumlahhari() {
            var dari = $("#tgl_izin_dari").val();
            var sampai = $("#tgl_izin_sampai").val();
            var date1 = new Date(dari);
            var date2 = new Date(sampai);

            var Difference_In_Time = date2.getTime() - date1.getTime();
            var Difference_In_Days = Difference_In_Time / (1000 * 3600 * 24);

            var jmlhari = 0;
            if (dari !== "" && sampai !== "") {
                jmlhari = Difference_In_Days + 1;
            }

            $("#jml_hari").val(jmlhari + " Hari");
        }

        $("#tgl_izin_dari, #tgl_izin_sampai").change(function() {
            loadjumlahhari();
        });

        // Validasi form sebelum submit
        $("#frmIzin").submit(function() {
            var tgl_izin_dari = $("#tgl_izin_dari").val();
            var tgl_izin_sampai = $("#tgl_izin_sampai").val();
            var keterangan = $("#keterangan").val();
            var file = $("#fileuploadInput")[0].files[0];

            if (tgl_izin_dari == "" || tgl_izin_sampai == "") {
                Swal.fire({
                    title: 'Oops !',
                    text: 'Tanggal Harus Diisi',
                    icon: 'warning'
                });
                return false;
            } else if (keterangan == "") {
                Swal.fire({
                    title: 'Oops !',
                    text: 'Keterangan Harus Diisi',
                    icon: 'warning'
                });
                return false;
            } else if (file && file.size > 500 * 1024) { // 500 KB
                Swal.fire({
                    title: 'Oops !',
                    text: 'Ukuran file maksimal 500 KB',
                    icon: 'warning'
                });
                return false;
            }
        });
    });
</script>
@endpush
