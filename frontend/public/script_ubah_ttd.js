document.addEventListener('DOMContentLoaded', function() {
    const editSignatureBtn = document.getElementById('edit-signature-btn');
    const editSignatureContainer = document.getElementById('edit-signature-container');
    const cancelEditSignatureBtn = document.getElementById('cancel-edit-signature');
    const editSignaturePadCanvas = document.getElementById('edit-signature-pad');
    const clearNewSignatureBtn = document.getElementById('clear-new-signature');
    const editSignatureDataInput = document.getElementById('edit-signature-data');
    const editSignatureForm = document.getElementById('edit-signature-form');
    let signaturePad = null;

    // Tampilkan form edit saat tombol "Ubah Tanda Tangan" diklik
    if (editSignatureBtn && editSignatureContainer) {
        editSignatureBtn.addEventListener('click', function() {
            editSignatureContainer.style.display = 'block';
            if (editSignatureForm) editSignatureForm.style.display = 'block';
            // Inisialisasi SignaturePad jika belum
            if (editSignaturePadCanvas && !signaturePad) {
                signaturePad = new SignaturePad(editSignaturePadCanvas, {
                    penColor: 'rgb(0,0,0)'
                });
                resizeCanvas();
            } else if (signaturePad) {
                signaturePad.clear();
            }
        });
    }

    // Sembunyikan form edit saat tombol "Batal" diklik
    if (cancelEditSignatureBtn && editSignatureContainer) {
        cancelEditSignatureBtn.addEventListener('click', function() {
            editSignatureContainer.style.display = 'none';
            if (editSignatureForm) editSignatureForm.style.display = 'none';
            if (signaturePad) signaturePad.clear();
        });
    }

    // Hapus tanda tangan baru saat tombol "Hapus" diklik
    if (clearNewSignatureBtn && editSignaturePadCanvas) {
        clearNewSignatureBtn.addEventListener('click', function() {
            if (signaturePad) signaturePad.clear();
        });
    }

    // Simpan data tanda tangan ke input hidden saat submit
    if (editSignatureForm && editSignaturePadCanvas) {
        editSignatureForm.addEventListener('submit', function(e) {
            if (signaturePad && !signaturePad.isEmpty()) {
                const dataURL = signaturePad.toDataURL('image/png');
                editSignatureDataInput.value = dataURL;
            } else {
                alert('Mohon gambar tanda tangan baru Anda.');
                e.preventDefault();
            }
        });
    }

    // Resize canvas agar tajam di semua device
    function resizeCanvas() {
        if (!editSignaturePadCanvas) return;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        editSignaturePadCanvas.width = editSignaturePadCanvas.offsetWidth * ratio;
        editSignaturePadCanvas.height = editSignaturePadCanvas.offsetHeight * ratio;
        editSignaturePadCanvas.getContext('2d').scale(ratio, ratio);
        if (signaturePad) signaturePad.clear();
    }
    window.addEventListener('resize', resizeCanvas);
});