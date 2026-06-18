document.addEventListener('DOMContentLoaded', () => {
    
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('login-error');
            const btn = document.getElementById('btn-login');

            btn.disabled = true;
            btn.innerText = 'Memproses...';
            errorDiv.style.display = 'none';

            try {
                const response = await fetch('/api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include', // <--- TAMBAHKAN BARIS INI
                    body: JSON.stringify({ email, password })
                });

                if (response.ok) {
                    const data = await response.json();
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('userName', data.name);
                    
                    /* Simpan role (user/admin) dari backend */
                    localStorage.setItem('userRole', data.role);
                    
                    /* Cek role untuk menentukan arah halaman */
                    if (data.role === 'admin') {
                        window.location.href = 'admin.html';
                    } else {
                        window.location.href = 'index.html';
                    }
                } else if (response.status === 401) {
                    errorDiv.innerText = 'Email atau password salah.';
                    errorDiv.style.display = 'block';
                } else {
                    errorDiv.innerText = 'Terjadi kesalahan pada server.';
                    errorDiv.style.display = 'block';
                }
            } catch (err) {
                errorDiv.innerText = 'Gagal terhubung ke server.';
                errorDiv.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.innerText = 'Masuk';
            }
        });
    }

    document.querySelectorAll('.btn-toggle-pass').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = btn.previousElementSibling;
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
        } else {
            input.type = 'password';
            btn.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
            }
        });
    });

    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            const errorDiv = document.getElementById('register-error');
            const btn = document.getElementById('btn-register');

            if (password !== confirmPass) {
                errorDiv.innerText = 'Password tidak cocok.';
                errorDiv.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.innerText = 'Memproses...';
            errorDiv.style.display = 'none';

            try {
                const response = await fetch('/api/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ name, email, password })
                });

                if (response.status === 201) {
                    window.location.href = 'login.html';
                } else if (response.status === 409) {
                    errorDiv.innerText = 'Email sudah terdaftar.';
                    errorDiv.style.display = 'block';
                } else {
                    errorDiv.innerText = 'Gagal melakukan pendaftaran.';
                    errorDiv.style.display = 'block';
                }
            } catch (err) {
                errorDiv.innerText = 'Gagal terhubung ke server.';
                errorDiv.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.innerText = 'Daftar Akun';
            }
        });
    }

    const registerAdminForm = document.getElementById('register-admin-form');
    if (registerAdminForm) {
        registerAdminForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.getElementById('admin-name').value;
            const venue = document.getElementById('admin-venue').value;
            const city = document.getElementById('admin-city').value;
            const price = document.getElementById('admin-price').value;
            const email = document.getElementById('admin-email').value;
            const password = document.getElementById('admin-password').value;
            const confirmPass = document.getElementById('admin-confirm-password').value;
            const errorDiv = document.getElementById('register-admin-error');
            const btn = document.getElementById('btn-register-admin');

            if (password !== confirmPass) {
                errorDiv.innerText = 'Password tidak cocok.';
                errorDiv.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.innerText = 'Memproses...';
            errorDiv.style.display = 'none';

            try {
                const response = await fetch('/api/register_admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        name, email, password,
                        venue_name: venue, city, price_per_hour: Number(price),
                    }),
                });

                if (response.status === 201) {
                    const data = await response.json();
                    // register_admin.php already logged us in (session cookie).
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('userRole', data.role);
                    localStorage.setItem('userName', data.name);
                    window.location.href = 'admin.html';
                } else if (response.status === 409) {
                    errorDiv.innerText = 'Email sudah terdaftar.';
                    errorDiv.style.display = 'block';
                } else {
                    const err = await response.json().catch(() => ({}));
                    errorDiv.innerText = err.error || 'Gagal mendaftarkan venue.';
                    errorDiv.style.display = 'block';
                }
            } catch (err) {
                errorDiv.innerText = 'Gagal terhubung ke server.';
                errorDiv.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.innerText = 'Daftar Sebagai Mitra';
            }
        });
    }
});