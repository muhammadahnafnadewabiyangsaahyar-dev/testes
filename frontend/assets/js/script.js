// Toggle between login and signup forms
document.addEventListener('DOMContentLoaded', function() {
    const loginButton = document.getElementById('loginbutton');
    const signupButton = document.getElementById('signupbutton');
    const checkWhitelist = document.getElementById('check-whitelist');
    const signupForm = document.getElementById('signup');
    const loginForm = document.getElementById('login');
    const backToLoginBtn = document.getElementById('backToLoginBtn');
    const backToWhitelistBtn = document.getElementById('backToWhitelistBtn');
    const whitelistForm = document.getElementById('whitelistForm');
    const whitelistResult = document.getElementById('whitelist-result');
    const lanjutDaftarBtn = document.getElementById('lanjutDaftarBtn');
    const signupNama = document.getElementById('signup_nama');
    const signupPosisi = document.getElementById('signup_posisi');

    // Clear all form inputs on page load (KECUALI no_wa)
    const allInputs = document.querySelectorAll('input');
    allInputs.forEach(input => {
        // Jangan clear field no_wa atau field readonly
        if (input.id !== 'no_wa' && !input.hasAttribute('readonly')) {
            input.value = '';
        }
        input.setAttribute('autocomplete', 'off');
        
        // Add input event listener to handle label visibility
        input.addEventListener('input', function() {
            const label = this.nextElementSibling;
            if (label && label.tagName === 'LABEL') {
                if (this.value !== '') {
                    label.style.opacity = '0';
                    label.style.visibility = 'hidden';
                } else {
                    label.style.opacity = '1';
                    label.style.visibility = 'visible';
                }
            }
        });
        
        // Handle focus events
        input.addEventListener('focus', function() {
            const label = this.nextElementSibling;
            if (label && label.tagName === 'LABEL') {
                label.style.opacity = '0';
                label.style.visibility = 'hidden';
            }
        });
        
        // Handle blur events
        input.addEventListener('blur', function() {
            const label = this.nextElementSibling;
            if (label && label.tagName === 'LABEL' && this.value === '') {
                label.style.opacity = '1';
                label.style.visibility = 'visible';
            }
        });
    });

    // Show login form by default
    if (loginForm) {
        loginForm.style.display = 'block';
    }
    if (signupForm) {
        signupForm.style.display = 'none';
    }

    // Switch to login form
    if (loginButton) {
        loginButton.addEventListener('click', function() {
            signupForm.style.display = 'none';
            loginForm.style.display = 'block';
            // Clear login form
            const loginInputs = loginForm.querySelectorAll('input');
            loginInputs.forEach(input => {
                input.value = '';
                const label = input.nextElementSibling;
                if (label && label.tagName === 'LABEL') {
                    label.style.opacity = '1';
                    label.style.visibility = 'visible';
                }
            });
        });
    }

    // Switch to signup form
    if (signupButton) {
        signupButton.addEventListener('click', function() {
            loginForm.style.display = 'none';
            signupForm.style.display = 'none';
            checkWhitelist.style.display = 'block';
            whitelistResult.innerHTML = '';
            lanjutDaftarBtn.style.display = 'none';
            whitelistForm.reset();
        });
    }

    // Back to Login from Check Whitelist
    if (backToLoginBtn) {
        backToLoginBtn.addEventListener('click', function() {
            checkWhitelist.style.display = 'none';
            loginForm.style.display = 'block';
        });
    }

    // Back to Check Whitelist from Signup
    if (backToWhitelistBtn) {
        backToWhitelistBtn.addEventListener('click', function() {
            signupForm.style.display = 'none';
            checkWhitelist.style.display = 'block';
        });
    }

    // Handle whitelist check via AJAX
    if (whitelistForm) {
        whitelistForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const nama = document.getElementById('whitelist_nama').value.trim();
            whitelistResult.innerHTML = 'Mengecek...';
            lanjutDaftarBtn.style.display = 'none';
            fetch('whitelist.php?check=1&nama=' + encodeURIComponent(nama))
                .then(res => res.json())
                .then(data => {
                    if (data.found) {
                        whitelistResult.innerHTML = `<div style='color:green;'>Nama ditemukan di whitelist.<br><b>Nama:</b> ${data.nama_lengkap}<br><b>Posisi:</b> ${data.posisi}</div>`;
                        lanjutDaftarBtn.style.display = 'inline-block';
                        // Simpan ke input signup (readonly)
                        if (signupNama) signupNama.value = data.nama_lengkap;
                        if (signupPosisi) signupPosisi.value = data.posisi;
                    } else {
                        whitelistResult.innerHTML = `<div style='color:red;'>Nama tidak ditemukan di whitelist atau sudah terdaftar.</div>`;
                        lanjutDaftarBtn.style.display = 'none';
                    }
                })
                .catch(() => {
                    whitelistResult.innerHTML = '<span style="color:red;">Gagal menghubungi server.</span>';
                    lanjutDaftarBtn.style.display = 'none';
                });
        });
    }

    // Lanjut ke form registrasi setelah whitelist OK
    if (lanjutDaftarBtn) {
        lanjutDaftarBtn.addEventListener('click', function() {
            checkWhitelist.style.display = 'none';
            signupForm.style.display = 'block';
        });
    }
});
