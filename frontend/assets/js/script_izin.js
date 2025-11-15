document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM siap, skrip berjalan!");

    // --- Bagian Logika Formulir Izin ---
    const applyIzinButton = document.getElementById('btn-apply'); // Tombol "Ajukan Surat Izin" awal
    console.log("Tombol 'Ajukan':", applyIzinButton);
    const izinFormContainer = document.getElementById('form-container'); // Div yang berisi form
    console.log("Container Form:", izinFormContainer);
    const izinInfoContainer = document.querySelector('.content-container:not(#form-container)'); 
    const izinForm = document.querySelector('.form-surat-izin'); // Elemen <form>

    // Tampilkan formulir saat tombol "Ajukan Surat Izin" awal diklik
    if (applyIzinButton) {
        applyIzinButton.addEventListener('click', function() {
            console.log("Tombol diklik!");
            if (izinInfoContainer) izinInfoContainer.style.display = 'none'; // Sembunyikan info
            console.log("Menampilkan form container...");
            if (izinFormContainer) izinFormContainer.style.display = 'block'; // Tampilkan form
        });
    } else {
        // Jika tombol tidak ditemukan saat halaman dimuat
        console.error("ERROR: Tombol 'Ajukan' (id: btn-apply) tidak ditemukan saat DOM siap!");
    }

    // --- Bagian Logika Signature Pad ---
    const canvas = document.getElementById('signature-pad'); // Dapatkan elemen canvas
    const clearButton = document.getElementById('clear-signature'); // Dapatkan tombol hapus
    const hiddenInput = document.getElementById('signature-data'); // Dapatkan input tersembunyi
    let signaturePad; // Variabel untuk menyimpan objek SignaturePad

    // Hanya inisialisasi jika elemen canvas ditemukan
    if (canvas) {
        // Buat objek SignaturePad baru menggunakan elemen canvas
        signaturePad = new SignaturePad(canvas, {
            penColor: "rgb(0, 0, 0)" // Atur warna pena (misal: hitam)
        });

        // Tambahkan fungsi untuk tombol 'Hapus'
        if (clearButton) {
            clearButton.addEventListener('click', function() {
                signaturePad.clear(); // Gunakan fungsi clear() dari library
            });
        }
    }

    // --- Menangani Pengiriman Formulir ---
    if (izinForm) {
        izinForm.addEventListener('submit', function(event) {
            // Cek apakah SignaturePad sudah diinisialisasi DAN apakah tanda tangan tidak kosong
            if (signaturePad && !signaturePad.isEmpty()) {
                // Ambil data gambar sebagai string Base64 PNG
                const dataURL = signaturePad.toDataURL('image/png');
                // Masukkan string Base64 ke dalam input tersembunyi
                hiddenInput.value = dataURL;
            } else if (canvas) { // Hanya validasi jika canvas memang seharusnya ada
                // Jika tanda tangan kosong
                alert("Mohon isi tanda tangan Anda.");
                event.preventDefault(); // Hentikan pengiriman formulir
            }
            // Jika tidak ada canvas (misal di halaman lain), biarkan formulir dikirim
        });
        if (canvas) { // Pastikan canvas ada sebelum memanggil
            resizeCanvas();
        }
    } // Tutup blok izinForm event listener

    // Fungsi resizeCanvas untuk signature pad utama
    function resizeCanvas() {
        if (!canvas) return; // Keluar jika canvas tidak ada
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        if (signaturePad) { // Hapus TTD hanya jika signaturePad sudah dibuat
            signaturePad.clear(); 
        } else if(canvas.getContext("2d")) { // Atau bersihkan manual jika belum
            canvas.getContext("2d").clearRect(0, 0, canvas.width, canvas.height);
        }
    }
    window.addEventListener("resize", resizeCanvas);

    // Sembunyikan info dan tombol, tampilkan form saja
    window.addEventListener('DOMContentLoaded', function() {
        var infoContainer = document.querySelector('.content-container');
        var formContainer = document.getElementById('form-container');
        if (infoContainer) infoContainer.style.display = 'none';
        if (formContainer) formContainer.style.display = '';
    });

}); // Akhir DOMContentLoaded