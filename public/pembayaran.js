document.addEventListener('DOMContentLoaded', async () => {
    
    // 1. Cek Autentikasi
    if (localStorage.getItem('isLoggedIn') !== 'true') {
        window.location.href = 'login.html';
        return;
    }

    // 2. Ekstrak ID dari URL
    const urlParams = new URLSearchParams(window.location.search);
    const bookingId = urlParams.get('booking_id');

    if (!bookingId) {
        alert("ID Pesanan tidak ditemukan.");
        window.location.href = 'riwayat.html';
        return;
    }

    const viewPayment = document.getElementById('view-payment');
    const viewSuccess = document.getElementById('view-success');
    const btnPay = document.getElementById('btn-pay');
    const errorDiv = document.getElementById('payment-error');
    const timerDisplay = document.getElementById('countdown-timer');

    let countdownInterval;

    // 3. Mengambil Data Pesanan Asli dari API
    try {
        const response = await fetch('/api/bookings.php', { credentials: 'include' });
        if (!response.ok) throw new Error('Gagal memuat data');
        
        const bookings = await response.json();
        const currentBooking = bookings.find(b => b.id == bookingId);

        if (!currentBooking) {
            alert("Pesanan tidak ditemukan atau bukan milikmu.");
            window.location.href = 'riwayat.html';
            return;
        }

        // Pindah tampilan jika sudah dibayar
        if (currentBooking.status === 'paid') {
            viewPayment.style.display = 'none';
            viewSuccess.style.display = 'block';
            return;
        }

        // Jika dibatalkan atau expired
        if (currentBooking.status !== 'pending') {
            const qrisBox = document.querySelector('.qris-box');
            if(qrisBox) {
                qrisBox.innerHTML = '<div style="color:red; text-align:center; padding: 20px;">Pesanan ini sudah tidak bisa dibayar (Kedaluwarsa/Batal).</div>';
            }
            if(timerDisplay) timerDisplay.style.display = 'none';
        } else {
            // Jalankan hitung mundur 15 menit jika status pending
            let duration = 900; 
            countdownInterval = setInterval(() => {
                let minutes = parseInt(duration / 60, 10);
                let seconds = parseInt(duration % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                if (timerDisplay) timerDisplay.innerText = `Sisa waktu: ${minutes}:${seconds}`;

                if (--duration < 0) {
                    clearInterval(countdownInterval);
                    if (timerDisplay) {
                        timerDisplay.innerText = "Waktu Habis!";
                        timerDisplay.style.background = "#fff";
                    }
                    if (btnPay) {
                        btnPay.disabled = true;
                        btnPay.innerText = "Pesanan Kedaluwarsa";
                        btnPay.style.background = "var(--line)";
                    }
                }
            }, 1000);
        }

        // 4. Render data ke ID HTML Split Layout
        const dateObj = new Date(currentBooking.date);
        const dateString = dateObj.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        
        document.getElementById('val-code').innerText = `#${currentBooking.code || currentBooking.id}`;
        document.getElementById('val-venue').innerText = currentBooking.venue_name;
        document.getElementById('val-court').innerText = currentBooking.court_label;
        document.getElementById('val-date').innerText = dateString;
        document.getElementById('val-time').innerText = `${String(currentBooking.start_hour).padStart(2,'0')}:00 - ${String(currentBooking.end_hour).padStart(2,'0')}:00`;
        
        const formatPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(currentBooking.price);
        document.getElementById('val-price').innerText = formatPrice;

    } catch (err) {
        console.error(err);
        alert("Gagal terhubung ke server.");
    }

    // 5. Eksekusi API Pembayaran
    if (btnPay) {
        btnPay.addEventListener('click', async () => {
            btnPay.disabled = true;
            btnPay.innerText = 'Memproses...';
            errorDiv.style.display = 'none';

            try {
                const response = await fetch('/api/payments.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ booking_id: parseInt(bookingId) })
                });

                if (response.status === 201) {
                    // Hentikan timer dan tampilkan layar sukses
                    if(countdownInterval) clearInterval(countdownInterval);
                    viewPayment.style.display = 'none';
                    viewSuccess.style.display = 'block';
                } else {
                    errorDiv.innerText = 'Gagal memproses pembayaran. Silakan coba lagi.';
                    errorDiv.style.display = 'block';
                    btnPay.disabled = false;
                    btnPay.innerText = 'Cek Status Pembayaran';
                }
            } catch (err) {
                errorDiv.innerText = 'Terjadi kesalahan jaringan.';
                errorDiv.style.display = 'block';
                btnPay.disabled = false;
                btnPay.innerText = 'Cek Status Pembayaran';
            }
        });
    }
});