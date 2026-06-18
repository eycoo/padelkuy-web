document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('detail-container');
    if (!container) return;

    const params = new URLSearchParams(window.location.search);
    const venueId = params.get('id');
    if (!venueId) {
        container.innerHTML = "<p style='padding:24px'>Venue tidak ditemukan.</p>";
        return;
    }

    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"];
    const pad = (n) => String(n).padStart(2, '0');

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let calMonth = today.getMonth();
    let calYear = today.getFullYear();
    let selectedDate = new Date(today);          // tanggal yang dipesan

    let venue = null;                            // meta venue (nama, deskripsi, ...)
    let courts = [];                             // dari /api/availability.php
    let picked = null;                           // {court_id, hour, price}

    const isoDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    // --- API ---------------------------------------------------------------

    async function loadVenue() {
        const res = await fetch('/api/venues.php');
        if (!res.ok) throw new Error('venues');
        const list = await res.json();
        venue = list.find(v => String(v.id) === String(venueId)) || null;
    }

    async function loadAvailability() {
        const res = await fetch(`/api/availability.php?venue_id=${venueId}&date=${isoDate(selectedDate)}`);
        if (!res.ok) throw new Error('availability');
        const data = await res.json();
        courts = data.courts || [];
        picked = null;
    }

    // --- Render ------------------------------------------------------------

    function renderDetail() {
        if (!venue) {
            container.innerHTML = "<p style='padding:24px'>Gagal memuat data venue.</p>";
            return;
        }

        const img = venue.main_image_path || venue.image_path || 'assets/images/court-1-image.jpeg';

        container.innerHTML = `
            <div class="venue-info">
                <div class="venue-header">
                    <h1>${venue.name}</h1>
                    <p class="venue-address">${venue.city}${venue.tag ? ' · ' + venue.tag : ''}</p>
                    <div class="venue-gallery">
                        <img src="${img}" alt="Main" class="gallery-img img-main" onerror="this.style.display='none'">
                    </div>
                </div>
                <div class="venue-desc">
                    <h3>About this court</h3>
                    <p>${venue.description || 'Belum ada deskripsi untuk venue ini.'}</p>
                </div>
            </div>

            <div class="booking-section">
                <div class="booking-header">
                    <h2>Mau main hari apa?</h2>
                    <h3 class="selected-date-label" id="selected-date-text"></h3>
                </div>
                <div class="booking-content">
                    <div class="inline-calendar">
                        <div class="calendar-header">
                            <button id="detail-prev-month">&lt;</button>
                            <span id="detail-month-year"></span>
                            <button id="detail-next-month">&gt;</button>
                        </div>
                        <div class="calendar-grid" id="detail-calendar-grid"></div>
                    </div>
                    <div class="court-list" id="court-list"></div>
                </div>
            </div>
        `;
        renderCourts();
        renderCalendar();
        renderSelectedLabel();
    }

    function renderSelectedLabel() {
        const el = document.getElementById('selected-date-text');
        if (el) el.innerText = `${selectedDate.getDate()} ${monthNames[selectedDate.getMonth()]} ${selectedDate.getFullYear()}`;
    }

    function renderCourts() {
        const list = document.getElementById('court-list');
        if (!list) return;

        if (courts.length === 0) {
            list.innerHTML = "<p class='empty-state'>Tidak ada lapangan untuk venue ini.</p>";
            return;
        }

        list.innerHTML = courts.map(c => {
            const slots = (c.slots || []).map(s => `
                <div class="slot-btn ${s.taken ? 'booked' : ''}"
                     data-court="${c.id}" data-hour="${s.hour}" data-price="${s.price}">
                    <span class="slot-time">${pad(s.hour)}:00 - ${pad(s.hour + 1)}:00</span>
                    <span class="slot-price">${s.taken ? 'Booked' : 'Rp ' + (s.price / 1000) + 'k'}</span>
                </div>
            `).join('');

            return `
                <div class="court-item" data-court="${c.id}">
                    <div class="court-header">
                        <h4>${c.label}</h4>
                        <button class="btn-cek-jadwal">Cek Jadwal</button>
                    </div>
                    <div class="court-body-expand">
                        <div class="court-slots">${slots}</div>
                        <div class="court-action">
                            <button class="btn-konfirmasi" disabled>Konfirmasi &amp; Bayar</button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderCalendar() {
        const monthYear = document.getElementById('detail-month-year');
        const grid = document.getElementById('detail-calendar-grid');
        if (!grid) return;

        monthYear.innerText = `${monthNames[calMonth]} ${calYear}`;
        let html = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']
            .map(d => `<div class="day-label">${d}</div>`).join('');

        const firstDay = new Date(calYear, calMonth, 1).getDay();
        const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();

        for (let i = 0; i < firstDay; i++) html += `<div class="date-cell past empty"></div>`;

        for (let i = 1; i <= daysInMonth; i++) {
            const d = new Date(calYear, calMonth, i);
            const isPast = d < today ? 'past' : '';
            const isSel = (i === selectedDate.getDate() && calMonth === selectedDate.getMonth()
                && calYear === selectedDate.getFullYear()) ? 'selected' : '';
            html += `<div class="date-cell ${isPast} ${isSel}" data-day="${i}">${i}</div>`;
        }
        grid.innerHTML = html;
    }

    // --- Events ------------------------------------------------------------

    container.addEventListener('click', async (e) => {
        // expand/collapse jadwal court
        if (e.target.classList.contains('btn-cek-jadwal')) {
            e.target.closest('.court-item').classList.toggle('expanded');
            return;
        }

        // pilih slot
        const slot = e.target.closest('.slot-btn');
        if (slot && !slot.classList.contains('booked')) {
            const courtItem = slot.closest('.court-item');
            // hanya satu slot terpilih di seluruh halaman
            container.querySelectorAll('.slot-btn.selected').forEach(b => b.classList.remove('selected'));
            container.querySelectorAll('.btn-konfirmasi').forEach(b => b.setAttribute('disabled', ''));
            slot.classList.add('selected');
            picked = {
                court_id: Number(slot.dataset.court),
                hour: Number(slot.dataset.hour),
                price: Number(slot.dataset.price),
            };
            courtItem.querySelector('.btn-konfirmasi').removeAttribute('disabled');
            return;
        }

        // konfirmasi & bayar -> buat booking asli
        if (e.target.classList.contains('btn-konfirmasi')) {
            if (!picked) return;
            const btn = e.target;
            btn.disabled = true;
            btn.innerText = 'Memproses...';
            try {
                const res = await fetch('/api/bookings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        court_id: picked.court_id,
                        date: isoDate(selectedDate),
                        start_hour: picked.hour,
                        end_hour: picked.hour + 1,
                    }),
                });

                if (res.status === 201) {
                    const booking = await res.json();
                    window.location.href = `pembayaran.html?booking_id=${booking.id}`;
                    return;
                }
                if (res.status === 401) {
                    alert('Silakan login dulu untuk memesan.');
                    window.location.href = 'login.html';
                    return;
                }
                if (res.status === 409) {
                    alert('Slot ini baru saja dipesan orang lain. Memuat ulang jadwal.');
                    await loadAvailability();
                    renderCourts();
                } else {
                    alert('Gagal membuat pesanan. Coba lagi.');
                }
            } catch (err) {
                alert('Gagal terhubung ke server.');
            }
            btn.disabled = false;
            btn.innerText = 'Konfirmasi & Bayar';
            return;
        }

        // navigasi bulan
        if (e.target.id === 'detail-prev-month') {
            calMonth--; if (calMonth < 0) { calMonth = 11; calYear--; }
            renderCalendar(); return;
        }
        if (e.target.id === 'detail-next-month') {
            calMonth++; if (calMonth > 11) { calMonth = 0; calYear++; }
            renderCalendar(); return;
        }

        // pilih tanggal -> refetch availability
        const cell = e.target.closest('.date-cell');
        if (cell && !cell.classList.contains('past') && !cell.classList.contains('empty')) {
            selectedDate = new Date(calYear, calMonth, Number(cell.dataset.day));
            renderSelectedLabel();
            try {
                await loadAvailability();
                renderCourts();
                renderCalendar();
            } catch (err) {
                document.getElementById('court-list').innerHTML =
                    "<p class='empty-state'>Gagal memuat jadwal.</p>";
            }
        }
    });

    // --- Init --------------------------------------------------------------

    (async () => {
        try {
            await loadVenue();
            await loadAvailability();
            renderDetail();
        } catch (err) {
            container.innerHTML = "<p style='padding:24px'>Gagal memuat data venue.</p>";
        }
    })();
});
