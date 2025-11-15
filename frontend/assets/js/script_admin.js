document.addEventListener('DOMContentLoaded', function() {
    console.log("Admin script loaded."); // Log untuk memastikan skrip dimuat

    document.querySelectorAll('.approve-btn, .reject-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const action = this.classList.contains('approve-btn') ? 'approve' : 'reject';
            const rowId = this.dataset.id; // Menggunakan dataset.id lebih modern

            console.log(`Mengirim: ID=${rowId}, Aksi=${action}`); // Log data yang dikirim

            fetch('proses_approve.php', { // <-- Perbaikan 1: Nama file PHP
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                // Menggunakan URLSearchParams lebih aman untuk encoding
                body: new URLSearchParams({
                    'pengajuan_id': rowId, // <-- Perbaikan 2: Nama parameter ID
                    'action': action
                })
            })
            .then(response => {
                if (!response.ok) { // Cek jika status HTTP bukan 2xx
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json(); // Lanjutkan parsing JSON
            })
            .then(data => {
                console.log("Respons Server:", data); // Log respons
                if (data.success) {
                    // Hapus baris dari tabel
                    const row = document.getElementById(`row-${rowId}`);
                    if (row) {
                        row.remove();
                        console.log(`Baris ${rowId} dihapus.`); // Log penghapusan
                    }
                    // Opsional: Tampilkan pesan sukses singkat
                    // alert(`Pengajuan ${rowId} berhasil di-${action}.`); 
                } else {
                    alert('Gagal memproses permintaan: ' + (data.message || 'Error tidak diketahui'));
                }
            })
            .catch(error => {
                console.error('Error Fetch:', error);
                alert('Terjadi kesalahan saat menghubungi server.');
            });
        });
    });

});