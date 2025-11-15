// Menunggu seluruh halaman dimuat sebelum menjalankan skrip
document.addEventListener("DOMContentLoaded", () => {

    // --- Ambil Elemen-elemen Penting ---
    const video = document.getElementById('kamera-preview');
    const canvas = document.getElementById('kamera-canvas');
    const btnAbsenMasuk = document.getElementById('btn-absen-masuk');
    const btnAbsenKeluar = document.getElementById('btn-absen-keluar');
    const statusLokasi = document.getElementById('status-lokasi');
    
    // Ambil form tersembunyi
    const formAbsensi = document.getElementById('form-absensi');
    const inputLat = document.getElementById('input-latitude');
    const inputLon = document.getElementById('input-longitude');
    const inputTipe = document.getElementById('input-tipe-absen');
    const inputFoto = document.getElementById('input-foto-base64');

    let koordinatPengguna = null;
    let streamKamera = null;
    let tipeAbsenSaatIni = 'masuk'; // Default
    let cameraActivated = false; // Flag untuk menandai apakah kamera sudah diaktifkan

    // --- Fungsi Utama 1: Memulai Kamera ---
    async function startCamera() {
        // Cek apakah tombol absen ada (jika sudah absen keluar, tombol tidak ada)
        if (!btnAbsenMasuk && !btnAbsenKeluar) {
            statusLokasi.textContent = "Absensi hari ini sudah selesai.";
            return;
        }

        // Hanya jalankan jika belum ada stream dan belum diaktifkan
        if (streamKamera || cameraActivated) return;

        try {
            statusLokasi.textContent = "Meminta izin kamera...";
            // Minta izin kamera (dan audio, meskipun tidak dipakai)
            streamKamera = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user' // Prioritaskan kamera depan
                },
                audio: false
            });
            
            video.srcObject = streamKamera;
            video.play();
            cameraActivated = true;
            statusLokasi.textContent = "Kamera aktif. Silakan ambil foto untuk absensi.";
            
        } catch (err) {
            console.error("Error Kamera:", err);
            statusLokasi.textContent = "Error: Kamera tidak diizinkan atau tidak ditemukan.";
            if (btnAbsenMasuk) {
                btnAbsenMasuk.disabled = true;
                btnAbsenMasuk.textContent = "Kamera Diblokir";
            }
            if (btnAbsenKeluar) {
                btnAbsenKeluar.disabled = true;
                btnAbsenKeluar.textContent = "Kamera Diblokir";
            }
        }
    }

    // --- Fungsi Utama 2: Mendapatkan Lokasi ---
    function setupLokasi() {
        // Cek apakah tombol absen ada
        if (!btnAbsenMasuk && !btnAbsenKeluar) return;

        const locationBox = document.getElementById('location-status-box');
        const locationText = document.getElementById('location-status-text');

        if ('geolocation' in navigator) {
            if (locationBox) locationBox.style.display = 'block';
            if (locationText) locationText.textContent = "Mendeteksi lokasi Anda...";

            navigator.geolocation.getCurrentPosition(
                async (posisi) => {
                    // Sukses dapat lokasi
                    koordinatPengguna = {
                        latitude: posisi.coords.latitude,
                        longitude: posisi.coords.longitude
                    };

                    // Validate location against branch
                    const locationValid = await validateLocation(koordinatPengguna);

                    if (locationValid.valid) {
                        if (locationText) locationText.textContent = `✅ Lokasi valid - ${locationValid.branch}`;
                        if (locationBox) {
                            locationBox.style.background = '#E8F5E8';
                            locationBox.style.borderColor = '#4CAF50';
                        }
                        statusLokasi.textContent = "Lokasi valid. Kamera siap untuk foto.";

                        // Aktifkan kamera dan tombol jika lokasi sudah siap
                        startCamera();
                        if (btnAbsenMasuk) btnAbsenMasuk.disabled = false;
                        if (btnAbsenKeluar) btnAbsenKeluar.disabled = false;
                    } else {
                        if (locationText) locationText.textContent = `❌ ${locationValid.message}`;
                        if (locationBox) {
                            locationBox.style.background = '#FFEBEE';
                            locationBox.style.borderColor = '#F44336';
                        }
                        statusLokasi.textContent = locationValid.message;
                        if (btnAbsenMasuk) {
                            btnAbsenMasuk.disabled = true;
                            btnAbsenMasuk.textContent = "Lokasi Tidak Valid";
                        }
                        if (btnAbsenKeluar) {
                            btnAbsenKeluar.disabled = true;
                            btnAbsenKeluar.textContent = "Lokasi Tidak Valid";
                        }
                    }

                    console.log("Lokasi:", koordinatPengguna, "Valid:", locationValid.valid);
                },
                (err) => {
                    // Gagal dapat lokasi
                    console.error("Error Lokasi:", err);
                    if (locationText) locationText.textContent = "❌ Lokasi tidak diizinkan atau gagal dideteksi.";
                    if (locationBox) {
                        locationBox.style.background = '#FFEBEE';
                        locationBox.style.borderColor = '#F44336';
                    }
                    statusLokasi.textContent = "Error: Lokasi tidak diizinkan atau gagal dideteksi.";
                    btnAbsenMasuk.disabled = true;
                    btnAbsenKeluar.disabled = true;
                    btnAbsenMasuk.textContent = "Lokasi Diblokir";
                    btnAbsenKeluar.textContent = "Lokasi Diblokir";
                },
                {
                    enableHighAccuracy: true, // Minta akurasi tinggi
                    timeout: 15000,           // Batas waktu 15 detik
                    maximumAge: 300000        // Cache 5 menit
                }
            );
        } else {
            if (locationText) locationText.textContent = "❌ Geolocation tidak didukung di browser ini.";
            if (locationBox) {
                locationBox.style.display = 'block';
                locationBox.style.background = '#FFEBEE';
                locationBox.style.borderColor = '#F44336';
            }
            statusLokasi.textContent = "Error: Geolocation tidak didukung di browser ini.";
            btnAbsenMasuk.disabled = true;
            btnAbsenKeluar.disabled = true;
        }
    }

    // --- Fungsi Utama 3: Mengambil Foto (Capture) ---
    function ambilFoto() {
        // Set ukuran canvas sesuai ukuran video
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        // "Gambar" frame video saat ini ke canvas
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Konversi gambar di canvas ke format data URL (Base64)
        // Mulai dengan kualitas 80%
        let base64 = canvas.toDataURL('image/jpeg', 0.8);
        
        // Check size - jika terlalu besar, compress lebih lanjut
        let sizeBytes = (base64.length * 0.75); // Rough estimate
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (sizeBytes > maxSize) {
            // Coba kualitas 60%
            base64 = canvas.toDataURL('image/jpeg', 0.6);
            sizeBytes = (base64.length * 0.75);
            
            if (sizeBytes > maxSize) {
                // Coba kualitas 40% (last resort)
                base64 = canvas.toDataURL('image/jpeg', 0.4);
            }
        }
        
        return base64;
    }

    // --- Fungsi Utama 4: Menghentikan Kamera ---
    function stopCamera() {
        if (streamKamera) {
            streamKamera.getTracks().forEach(track => track.stop());
            streamKamera = null;
        }
    }

    // Location validation function
    async function validateLocation(coords) {
        try {
            const response = await fetch('api_location_validate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    latitude: coords.latitude,
                    longitude: coords.longitude
                })
            });

            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Location validation error:', error);
            return {
                valid: false,
                message: 'Gagal memverifikasi lokasi. Silakan coba lagi.'
            };
        }
    }

    // --- Menjalankan Fungsi ---

    // 1. Cek apakah tombol absen ada di halaman
    if (btnAbsenMasuk && btnAbsenKeluar) {
        // --- Status awal tombol ---
        // Status absen user didapat dari atribut data-status pada salah satu tombol (set di PHP)
        // data-status: 'belum_masuk', 'sudah_masuk', 'sudah_keluar'
        const statusAbsen = btnAbsenMasuk.getAttribute('data-status') || 'belum_masuk';
        if (statusAbsen === 'belum_masuk') {
            btnAbsenMasuk.disabled = false;
            btnAbsenKeluar.disabled = true;
        } else if (statusAbsen === 'sudah_masuk') {
            btnAbsenMasuk.disabled = true;
            btnAbsenKeluar.disabled = false;
        } else if (statusAbsen === 'sudah_keluar') {
            // FIX: ALLOW MULTIPLE ABSEN KELUAR
            // User yang sudah absen keluar tetap bisa absen keluar lagi untuk update waktu
            btnAbsenMasuk.disabled = true;
            btnAbsenKeluar.disabled = false; // Aktifkan tombol keluar lagi
            btnAbsenKeluar.textContent = 'Update Absen Keluar';
            btnAbsenKeluar.style.background = '#FF9800'; // Warna berbeda untuk update
        } else {
            btnAbsenMasuk.disabled = true;
            btnAbsenKeluar.disabled = true;
        }
        setupLokasi();
        // Camera akan dimulai dari setupLokasi() setelah validasi lokasi berhasil
        // Handler Absen Masuk
        if (btnAbsenMasuk) {
            btnAbsenMasuk.addEventListener('click', async () => {
                if (!koordinatPengguna) {
                    alert("Lokasi belum siap. Mohon tunggu atau izinkan lokasi.");
                    return;
                }

                if (!cameraActivated) {
                    alert("Kamera belum aktif. Mohon tunggu hingga kamera siap.");
                    return;
                }

                btnAbsenMasuk.disabled = true;
                btnAbsenMasuk.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Mengambil Foto...';

                try {
                    // Step 1: Capture photo
                    let fotoBase64 = ambilFoto();

                    btnAbsenMasuk.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
                    statusLokasi.textContent = 'Memproses absensi masuk...';

                    inputLat.value = koordinatPengguna.latitude;
                    inputLon.value = koordinatPengguna.longitude;
                    inputTipe.value = 'masuk';
                    inputFoto.value = fotoBase64;

                    const formData = new FormData(formAbsensi);
                    const response = await fetch('proses_absensi.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    if (result.status === 'success') {
                        btnAbsenMasuk.innerHTML = '✓ Absen Masuk Berhasil';
                        if (btnAbsenKeluar) {
                            btnAbsenKeluar.disabled = false;
                        }
                        btnAbsenMasuk.disabled = true;
                        statusLokasi.textContent = 'Silakan lakukan absen keluar saat pulang.';
                    } else {
                        alert(result.message || 'Terjadi error.');
                        btnAbsenMasuk.disabled = false;
                        btnAbsenMasuk.innerHTML = 'Absen Masuk';
                        window.location.reload();
                    }
                } catch (e) {
                    console.error('Absen masuk error:', e);
                    alert('Gagal mengirim absensi. Silakan coba lagi.');
                    btnAbsenMasuk.disabled = false;
                    btnAbsenMasuk.innerHTML = 'Absen Masuk';
                    window.location.reload();
                }
            });
        }
        // Handler Absen Keluar
        if (btnAbsenKeluar) {
            btnAbsenKeluar.addEventListener('click', async () => {
                if (!koordinatPengguna) {
                    alert("Lokasi belum siap. Mohon tunggu atau izinkan lokasi.");
                    return;
                }

                if (!cameraActivated) {
                    alert("Kamera belum aktif. Mohon tunggu hingga kamera siap.");
                    return;
                }

                // Validasi waktu absen keluar (maksimal jam 23:59:59)
                const currentHour = new Date().getHours();
                if (currentHour >= 0 && currentHour <= 6) {
                    alert("Absen keluar maksimal jam 23:59:59. Silakan absen keluar sebelum jam 00:00.");
                    return;
                }

                btnAbsenKeluar.disabled = true;
                btnAbsenKeluar.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Mengambil Foto...';

                try {
                    // Step 1: Capture photo
                    let fotoBase64 = ambilFoto();

                    btnAbsenKeluar.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
                    statusLokasi.textContent = 'Memproses absensi keluar...';

                    inputLat.value = koordinatPengguna.latitude;
                    inputLon.value = koordinatPengguna.longitude;
                    inputTipe.value = 'keluar';
                    inputFoto.value = fotoBase64;

                    const formData = new FormData(formAbsensi);
                    const response = await fetch('proses_absensi.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    if (result.status === 'success') {
                        // Cek apakah ada pesan custom (untuk update absen keluar)
                        if (result.message) {
                            statusLokasi.textContent = result.message;
                        }

                        // Jika next = konfirmasi_lembur, tampilkan modal
                        if (result.next === 'konfirmasi_lembur') {
                            // Tampilkan modal konfirmasi lembur
                            const modalLembur = document.getElementById('modal-lembur');
                            if (modalLembur) {
                                modalLembur.style.display = 'flex';
                            }
                            // Simpan absen_id jika ada
                            let absenId = result.absen_id ? result.absen_id : '';
                            // Handler tombol modal
                            const btnLemburYa = document.getElementById('btn-lembur-ya');
                            const btnLemburTidak = document.getElementById('btn-lembur-tidak');
                            
                            if (btnLemburYa) {
                                btnLemburYa.onclick = function() {
                                    if (absenId) {
                                        window.location.href = 'konfirmasi_lembur.php?absen_id=' + encodeURIComponent(absenId);
                                    } else {
                                        window.location.href = 'konfirmasi_lembur.php';
                                    }
                                };
                            }
                            
                            if (btnLemburTidak) {
                                btnLemburTidak.onclick = function() {
                                    window.location.href = 'absen.php';
                                };
                            }
                        } else if (result.next === 'done') {
                            // Absen keluar selesai tanpa lembur
                            btnAbsenKeluar.innerHTML = '✓ Selesai';
                            btnAbsenKeluar.disabled = false; // Tetap aktifkan untuk update lagi
                            btnAbsenKeluar.style.background = '#4CAF50'; // Hijau untuk selesai

                            // Jika ini update (ada message), tunjukkan pesan
                            if (!result.message) {
                                statusLokasi.textContent = 'Absensi hari ini selesai. Terima kasih!';
                            }

                            // Jangan stop kamera agar bisa update lagi
                        }

                        btnAbsenKeluar.innerHTML = 'Absen Keluar';
                    } else if (result.status === 'error' && (result.message === 'Not logged in' || result.message.toLowerCase().includes('unauthorized'))) {
                        alert('Sesi Anda telah habis. Silakan login kembali.');
                        window.location.href = 'index.php?error=notloggedin';
                    } else {
                        alert(result.message || 'Terjadi error.');
                        btnAbsenKeluar.disabled = false; // Re-enable untuk coba lagi
                        btnAbsenKeluar.innerHTML = 'Absen Keluar';
                    }
                } catch (e) {
                    console.error('Absen keluar error:', e);
                    alert('Gagal mengirim absensi. Silakan coba lagi.');
                    btnAbsenKeluar.disabled = false;
                    btnAbsenKeluar.innerHTML = 'Absen Keluar';
                    window.location.reload();
                }
            });
        }
    } else {
        statusLokasi.textContent = "Absensi hari ini sudah selesai.";
    }

    // --- Fitur: Ubah tombol otomatis setelah absen masuk tanpa reload ---
    window.ubahTombolAbsen = function(tipe) {
        if (!btnAbsenMasuk && !btnAbsenKeluar) return;
        if (tipe === 'keluar') {
            btnAbsenKeluar.setAttribute('data-tipe', 'keluar');
            btnAbsenKeluar.textContent = 'Absen Keluar';
            btnAbsenKeluar.disabled = false;
            // startCamera(); // Jangan reset kamera agar tidak gelap
        } else if (tipe === 'done') {
            btnAbsenMasuk.setAttribute('data-tipe', 'done');
            btnAbsenMasuk.textContent = 'Absensi Selesai';
            btnAbsenMasuk.disabled = true;
            stopCamera();
        }
    }

    // Membersihkan stream kamera jika pengguna meninggalkan halaman
    window.addEventListener('beforeunload', stopCamera);
});