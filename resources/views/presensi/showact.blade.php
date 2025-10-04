{{-- View ini dimuat secara AJAX ke dalam modal #actionSheetIconed dari halaman daftar izin --}}

<div class="modal-body" id="showact">
    <ul class="action-button-list">
        <li>
            {{-- LOGIKA TOMBOL EDIT DINAMIS --}}
            @if ($dataizin->status == "i")
            {{-- Izin Absen --}}
            <a href="/izinabsen/{{ $dataizin->kode_izin }}/edit" class="btn btn-list text-primary">
                <span>
                    <ion-icon name="create-outline"></ion-icon>
                    Edit
                </span>
            </a>
            @elseif($dataizin->status == "s")
            {{-- Izin Sakit --}}
            <a href="/izinsakit/{{ $dataizin->kode_izin }}/edit" class="btn btn-list text-primary">
                <span>
                    <ion-icon name="create-outline"></ion-icon>
                    Edit
                </span>
            </a>
            @elseif($dataizin->status == "c")
            {{-- Izin Cuti --}}
            <a href="/izincuti/{{ $dataizin->kode_izin }}/edit" class="btn btn-list text-primary">
                <span>
                    <ion-icon name="create-outline"></ion-icon>
                    Edit
                </span>
            </a>
            @elseif($dataizin->status == "d") 
            {{-- Izin DINAS LUAR (SOLUSI MASALAH ANDA) --}}
            <a href="/izindinas/{{ $dataizin->kode_izin }}/edit" class="btn btn-list text-primary">
                <span>
                    <ion-icon name="create-outline"></ion-icon>
                    Edit
                </span>
            </a>
            @endif
        </li>
        
        <li>
            {{-- TOMBOL DELETE --}}
            {{-- data-toggle dan data-target memicu modal konfirmasi #deleteConfirm --}}
            <a href="#" id="deletebutton" class="btn btn-list text-danger" 
                data-dismiss="modal" data-toggle="modal" data-target="#deleteConfirm">
                <span>
                    <ion-icon name="trash-outline"></ion-icon>
                    Delete
                </span>
            </a>
        </li>
    </ul>
</div>

<script>
    // Pastikan skrip ini diapit oleh tag <script>
    $(function() {
        // Ketika tombol "Delete" di modal aksi (showact) diklik
        $("#deletebutton").click(function(e) {
            e.preventDefault(); // Mencegah aksi default href="#"
            
            // Set URL yang benar untuk tombol "Hapus" di modal konfirmasi (#hapuspengajuan)
            $("#hapuspengajuan").attr('href', '/izin/' + '{{ $dataizin->kode_izin }}/delete');
            
            // Note: Tidak perlu memanggil data-toggle/data-target lagi karena sudah ada di tombolnya
        });
    });
</script>