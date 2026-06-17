// document.addEventListener('DOMContentLoaded', () => {
//     const container = document.getElementById('bookings-container');
//     const tabBtns = document.querySelectorAll('.tab-btn');

//     // Mencegah akses jika belum login
//     if (localStorage.getItem('isLoggedIn') !== 'true') {
//         alert('Kamu harus masuk untuk melihat riwayat pesanan.');
//         window.location.href = 'login.html';
//         return;
//     }

//     // Mock Data Sementara
//     // Status di sistem kita: 'pending', 'paid', 'cancelled', 'expired'
//     const mockBookings = [
//         {
//             id: 1,
//             code: "AD19214SKAd",
//             venue_name: "The G Club Padel",
//             court_label: "Court 1",
//             date: "2026-04-26",
//             start_hour: 18,
//             end_hour: 20,
//             hours: 2,
//             price: 360000,
//             status: "paid" // Akan jadi SELESAI (Hijau)
//         },
//         {
//             id: 2,
//             code: "KCASJ1723SA",
//             venue_name: "The G Club Padel",
//             court_label: "Court 1",
//             date: "2026-04-27",
//             start_hour: 14,
//             end_hour: 15,
//             hours: 1,
//             price: 180000,
//             status: "pending" // Akan jadi PENDING (Kuning), muncul tombol
//         },
//         {
//             id: 3,
//             code: "X99ZQA111XX",
//             venue_name: "Senayan Padel",
//             court_label: "Court A",
//             date: "2026-04-20",
//             start_hour: 8,
//             end_hour: 10,
//             hours: 2,
//             price: 300000,
//             status: "cancelled" // Akan jadi DIBATALKAN (Merah)
//         }
//     ];

//     let currentFilter = 'all';

//     const renderBookings = () => {
//         // Logika Filter Tab
//         const filtered = mockBookings.filter(b => {
//             if (currentFilter === 'all') return true;
//             if (currentFilter === 'pending') return b.status === 'pending';
//             // Tab "Selesai" menampung yang 'paid', 'cancelled', dan 'expired'
//             if (currentFilter === 'completed') return ['paid', 'cancelled', 'expired'].includes(b.status);
//             return true;
//         });

//         if (filtered.length === 0) {
//             container.innerHTML = `<div class="empty-state">Tidak ada pesanan di kategori ini.</div>`;
//             return;
//         }

//         container.innerHTML = filtered.map(b => {
//             // Logika Warna Badge sesuai OPSI A
//             let badgeClass = '';
//             let badgeText = '';
            
//             if (b.status === 'paid') {
//                 badgeClass = 'badge-success';
//                 badgeText = 'SELESAI';
//             } else if (b.status === 'pending') {
//                 badgeClass = 'badge-pending';
//                 badgeText = 'PENDING';
//             } else {
//                 badgeClass = 'badge-danger';
//                 badgeText = 'DIBATALKAN';
//             }

//             // Memformat Tanggal & Harga
//             const dateObj = new Date(b.date);
//             const dateString = dateObj.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
//             const formatPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(b.price);

//             // Mengembalikan HTML untuk 1 Kartu
//             return `
//                 <div class="booking-card">
//                     <div class="booking-header">
//                         <div class="booking-code">#${b.code}</div>
//                         <div class="badge ${badgeClass}">${badgeText}</div>
//                     </div>
//                     <div class="booking-details">
//                         <div><strong>Venue:</strong> ${b.venue_name} - ${b.court_label}</div>
//                         <div><strong>Jadwal:</strong> ${dateString} | ${String(b.start_hour).padStart(2,'0')}:00 - ${String(b.end_hour).padStart(2,'0')}:00 (${b.hours} Jam)</div>
//                         <div><strong>Total:</strong> ${formatPrice}</div>
//                     </div>
//                     ${b.status === 'pending' ? `
//                     <div class="booking-footer">
//                         <button class="btn-pay" onclick="window.location.href='pembayaran.html?booking_id=${b.id}'">Selesaikan Pembayaran</button>
//                     </div>` : ''}
//                 </div>
//             `;
//         }).join('');
//     };

//     // Jalankan render pertama kali
//     renderBookings();

//     // Event Listener untuk klik Tab Filter
//     tabBtns.forEach(btn => {
//         btn.addEventListener('click', (e) => {
//             // Hapus class active dari semua tab, lalu tambahkan ke yang diklik
//             tabBtns.forEach(b => b.classList.remove('active'));
//             e.target.classList.add('active');
            
//             // Perbarui filter dan render ulang
//             currentFilter = e.target.getAttribute('data-filter');
//             renderBookings();
//         });
//     });
// });

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('bookings-container');
    const tabBtns = document.querySelectorAll('.tab-btn');

    // Mencegah akses jika belum login
    if (localStorage.getItem('isLoggedIn') !== 'true') {
        alert('Kamu harus masuk untuk melihat riwayat pesanan.');
        window.location.href = 'login.html';
        return;
    }

    let allBookings = []; // Tempat menyimpan data asli dari database
    let currentFilter = 'all';

    // 1. Fungsi Fetch Data Asli dari Backend
    const fetchBookings = async () => {
        container.innerHTML = '<div class="empty-state">Memuat pesanan...</div>';
        try {
            const response = await fetch('/api/bookings.php', {
                method: 'GET',
                credentials: 'include' // Wajib agar PHP tahu siapa yang sedang login
            });

            if (response.status === 401) {
                // Sesi habis atau belum login
                localStorage.removeItem('isLoggedIn');
                localStorage.removeItem('userName');
                window.location.href = 'login.html';
                return;
            }

            if (response.ok) {
                allBookings = await response.json();
                renderBookings(); // Render setelah data didapat
            } else {
                container.innerHTML = '<div class="empty-state">Gagal mengambil data dari server.</div>';
            }
        } catch (err) {
            container.innerHTML = '<div class="empty-state">Gagal terhubung ke server.</div>';
        }
    };

    // 2. Fungsi Render Kartu ke HTML
    const renderBookings = () => {
        // Logika Filter Tab
        const filtered = allBookings.filter(b => {
            if (currentFilter === 'all') return true;
            if (currentFilter === 'pending') return b.status === 'pending';
            // Tab "Selesai" menampung yang 'paid', 'cancelled', dan 'expired'
            if (currentFilter === 'completed') return ['paid', 'cancelled', 'expired'].includes(b.status);
            return true;
        });

        if (filtered.length === 0) {
            container.innerHTML = `<div class="empty-state">Tidak ada pesanan di kategori ini.</div>`;
            return;
        }

        container.innerHTML = filtered.map(b => {
            // Logika Warna Badge
            let badgeClass = '';
            let badgeText = '';
            
            if (b.status === 'paid') {
                badgeClass = 'badge-success';
                badgeText = 'SELESAI';
            } else if (b.status === 'pending') {
                badgeClass = 'badge-pending';
                badgeText = 'PENDING';
            } else {
                badgeClass = 'badge-danger';
                badgeText = 'DIBATALKAN';
            }

            // Memformat Tanggal & Harga
            const dateObj = new Date(b.date);
            const dateString = dateObj.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            const formatPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(b.price);

            // Mengembalikan HTML untuk 1 Kartu
            return `
                <div class="booking-card">
                    <div class="booking-header">
                        <div class="booking-code">#${b.code || b.id}</div>
                        <div class="badge ${badgeClass}">${badgeText}</div>
                    </div>
                    <div class="booking-details">
                        <div><strong>Venue:</strong> ${b.venue_name} - ${b.court_label}</div>
                        <div><strong>Jadwal:</strong> ${dateString} | ${String(b.start_hour).padStart(2,'0')}:00 - ${String(b.end_hour).padStart(2,'0')}:00 (${b.hours} Jam)</div>
                        <div><strong>Total:</strong> ${formatPrice}</div>
                    </div>
                    ${b.status === 'pending' ? `
                    <div class="booking-footer">
                        <button class="btn-pay" onclick="window.location.href='pembayaran.html?booking_id=${b.id}'">Selesaikan Pembayaran</button>
                    </div>` : ''}
                    ${b.status === 'paid' ? `
                    <div class="booking-footer">
                        <a class="btn-receipt" href="/api/receipt.php?booking_id=${b.id}" target="_blank" rel="noopener">Download Kuitansi</a>
                    </div>` : ''}
                </div>
            `;
        }).join('');
    };

    // 3. Event Listener untuk klik Tab Filter
    tabBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            tabBtns.forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            
            currentFilter = e.target.getAttribute('data-filter');
            renderBookings(); // Render ulang tiap kali tab diganti
        });
    });

    // Panggil API saat halaman pertama kali dibuka
    fetchBookings();
});