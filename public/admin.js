document.addEventListener('DOMContentLoaded', () => {
    // --- Auth gate ---------------------------------------------------------
    if (localStorage.getItem('isLoggedIn') !== 'true' || localStorage.getItem('userRole') !== 'admin') {
        alert('Akses ditolak. Login sebagai admin dulu.');
        window.location.href = 'login.html';
        return;
    }
    document.getElementById('admin-name').innerText = localStorage.getItem('userName') || 'Admin';

    document.getElementById('btn-admin-logout').addEventListener('click', async () => {
        try { await fetch('/api/logout.php', { method: 'POST', credentials: 'include' }); } catch (e) {}
        localStorage.clear();
        window.location.href = 'login.html';
    });

    // --- Mappings between FE labels and stored enums -----------------------
    const BAND_TO_FE = { everyday: 'EVERYDAY', mon_fri: 'MON-FRI', sat_sun: 'SAT-SUN' };
    const hhmm = (h) => String(h).padStart(2, '0') + ':00';
    const msg = (text, ok = true) => {
        const el = document.getElementById('admin-msg');
        el.innerText = text;
        el.style.color = ok ? '#1a7f37' : '#c0392b';
    };

    // --- Editor state ------------------------------------------------------
    // images: thumbnail -> image_path, main -> main_image_path, detail0/1 -> gallery
    let state = blankVenue();
    let courts = [];

    function blankVenue() {
        return { id: null, name: '', city: '', price: 0, tag: '', description: '',
            images: { thumbnail: '', main: '', detail0: '', detail1: '' }, facilities: [] };
    }

    // --- My venue (one admin owns exactly one venue, ADR-0006) ------------
    // The admin endpoint already scopes to the caller's venue, so the list is
    // 0 or 1 entries. No selector — load "my venue", or a blank form to create
    // one if the admin somehow has none (e.g. after deleting it).
    async function loadMyVenue() {
        const res = await fetch('/api/admin/venues.php', { credentials: 'include' });
        const list = res.ok ? await res.json() : [];
        if (list.length) {
            await loadVenue(list[0].id);
        } else {
            state = blankVenue();
            courts = [];
            paintVenue();
            renderFacilities();
            renderCourts();
            msg('Belum ada venue — isi field lalu Simpan untuk membuatnya.');
        }
    }

    async function loadVenue(id) {
        const res = await fetch(`/api/admin/venues.php?id=${id}`, { credentials: 'include' });
        if (!res.ok) { msg('Gagal memuat venue.', false); return; }
        const v = await res.json();
        const gallery = v.images || [];
        state = {
            id: v.id, name: v.name || '', city: v.city || '', price: v.price_per_hour || 0,
            tag: v.tag || '', description: v.description || '',
            images: {
                thumbnail: v.image_path || '', main: v.main_image_path || '',
                detail0: gallery[0] || '', detail1: gallery[1] || '',
            },
            facilities: (v.facilities || []).slice(),
        };
        // courts + schedules
        const cRes = await fetch(`/api/admin/courts.php?venue_id=${id}`, { credentials: 'include' });
        const rawCourts = cRes.ok ? await cRes.json() : [];
        courts = [];
        for (const c of rawCourts) {
            const sRes = await fetch(`/api/admin/schedules.php?court_id=${c.id}`, { credentials: 'include' });
            const sched = sRes.ok ? await sRes.json() : [];
            courts.push({
                id: c.id, name: c.label, type: c.type === 'outdoor' ? 'Outdoor' : 'Indoor',
                schedules: sched.map(s => ({ day: BAND_TO_FE[s.day_band] || 'EVERYDAY',
                    start: hhmm(s.start_hour), end: hhmm(s.end_hour), price: s.price })),
            });
        }
        paintVenue();
        renderFacilities();
        renderCourts();
        msg(`Memuat "${state.name}".`);
    }

    // --- Paint venue scalar fields ----------------------------------------
    function paintVenue() {
        document.getElementById('venue-name-text').innerText = state.name;
        document.getElementById('venue-location-text').innerText = state.city;
        document.getElementById('venue-price').value = state.price || '';
        document.getElementById('venue-tag-text').innerText = state.tag;
        document.getElementById('venue-about-text').innerText = state.description;
        document.querySelectorAll('.image-slot').forEach(slot => paintSlot(slot));
    }

    function paintSlot(slot) {
        const role = slot.dataset.role;
        const path = state.images[role];
        const input = slot.querySelector('input');
        if (path) {
            slot.style.backgroundImage = `url('${path}')`;
            slot.style.backgroundSize = 'cover';
            slot.style.backgroundPosition = 'center';
            slot.style.color = 'transparent';
        } else {
            slot.style.backgroundImage = '';
            slot.style.color = '';
        }
        if (input) input.value = '';
    }

    // --- Image upload ------------------------------------------------------
    document.querySelectorAll('.image-slot input[type=file]').forEach(input => {
        input.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const slot = input.closest('.image-slot');
            const role = slot.dataset.role;
            const prev = slot.textContent;
            slot.textContent = 'Mengupload...';
            try {
                const fd = new FormData();
                fd.append('image', file);
                const res = await fetch('/api/admin/upload.php', { method: 'POST', body: fd, credentials: 'include' });
                if (res.status === 201) {
                    const { path } = await res.json();
                    state.images[role] = path;
                    paintSlot(slot);
                    msg('Gambar terupload. Jangan lupa Simpan.');
                } else {
                    const err = await res.json().catch(() => ({}));
                    slot.textContent = prev;
                    msg('Upload gagal: ' + (err.error || res.status), false);
                }
            } catch (err) {
                slot.textContent = prev;
                msg('Upload gagal: koneksi.', false);
            }
        });
    });

    // --- Facilities --------------------------------------------------------
    const facilityContainer = document.getElementById('facility-container');
    const facilityInput = document.getElementById('facility-input');

    function renderFacilities() {
        facilityContainer.innerHTML = state.facilities.map((f, i) => `
            <span class="chip facility-item">${f}
                <button class="btn-remove-chip" data-i="${i}" type="button">x</button>
            </span>`).join('');
    }

    facilityContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-remove-chip')) {
            state.facilities.splice(Number(e.target.dataset.i), 1);
            renderFacilities();
        }
    });

    facilityInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = facilityInput.value.trim();
            if (val && !state.facilities.includes(val)) {
                state.facilities.push(val);
                renderFacilities();
            }
            facilityInput.value = '';
        }
    });

    // --- Courts + schedules (local edit) ----------------------------------
    const courtsWrapper = document.getElementById('courts-wrapper');

    function renderCourts() {
        courtsWrapper.innerHTML = courts.map((c, i) => `
            <div class="admin-court-card">
                <button class="btn-delete-court" onclick="removeCourt(${i})" type="button">Hapus Lapangan</button>
                <div class="court-header">
                    <div class="editable-text court-name-edit" contenteditable="true" onblur="updateCourt(${i}, 'name', this.innerText)">${c.name}</div>
                    <div class="editable-text court-desc-edit" contenteditable="true" onblur="updateCourt(${i}, 'type', this.innerText)">${c.type}</div>
                </div>
                <div class="schedule-capsule-container">
                    ${c.schedules.map((s, j) => `
                        <div class="schedule-pill">
                            <select class="pill-input" onchange="updateSchedule(${i}, ${j}, 'day', this.value)" style="width: 120px;">
                                <option value="EVERYDAY" ${s.day === 'EVERYDAY' ? 'selected' : ''}>EVERYDAY</option>
                                <option value="MON-FRI" ${s.day === 'MON-FRI' ? 'selected' : ''}>MON-FRI</option>
                                <option value="SAT-SUN" ${s.day === 'SAT-SUN' ? 'selected' : ''}>SAT-SUN</option>
                            </select>
                            <input type="time" class="pill-input" value="${s.start}" onchange="updateSchedule(${i}, ${j}, 'start', this.value)" style="width: 65px;">
                            <input type="time" class="pill-input" value="${s.end}" onchange="updateSchedule(${i}, ${j}, 'end', this.value)" style="width: 65px;">
                            <input type="number" class="pill-input" value="${s.price}" placeholder="Harga" oninput="updateSchedule(${i}, ${j}, 'price', this.value)" style="width: 80px;">
                            <button class="btn-x" onclick="removeSchedule(${i}, ${j})" type="button">x</button>
                        </div>`).join('')}
                    <button class="btn-add-schedule" onclick="addSchedule(${i})" type="button">+ Jadwal</button>
                </div>
            </div>`).join('');
    }

    window.updateCourt = (i, k, v) => { courts[i][k] = v.trim(); };
    window.removeCourt = (i) => { courts.splice(i, 1); renderCourts(); };
    window.removeSchedule = (i, j) => { courts[i].schedules.splice(j, 1); renderCourts(); };
    window.addSchedule = (i) => { courts[i].schedules.push({ day: 'EVERYDAY', start: '07:00', end: '08:00', price: state.price || 100000 }); renderCourts(); };
    window.updateSchedule = (i, j, k, v) => { courts[i].schedules[j][k] = (k === 'price') ? Number(v) : v; };

    document.getElementById('btn-add-court').addEventListener('click', () => {
        courts.push({ id: null, name: 'Lapangan Baru', type: 'Indoor', schedules: [] });
        renderCourts();
    });

    // --- Save (bulk bundle) ------------------------------------------------
    document.getElementById('btn-save-venue').addEventListener('click', async () => {
        // read scalar fields from DOM
        state.name = document.getElementById('venue-name-text').innerText.trim();
        state.city = document.getElementById('venue-location-text').innerText.trim();
        state.price = Number(document.getElementById('venue-price').value);
        state.tag = document.getElementById('venue-tag-text').innerText.trim();
        state.description = document.getElementById('venue-about-text').innerText.trim();

        if (!state.name || !state.city) { msg('Nama dan kota wajib diisi.', false); return; }
        if (!(state.price > 0)) { msg('Harga per jam harus > 0.', false); return; }

        const gallery = [state.images.detail0, state.images.detail1].filter(Boolean);
        const bundle = {
            name: state.name, city: state.city, price_per_hour: state.price,
            tag: state.tag || null, description: state.description || null,
            image_path: state.images.thumbnail || null,
            main_image_path: state.images.main || null,
            facilities: state.facilities,
            images: gallery,
            courts: courts.map(c => ({
                ...(typeof c.id === 'number' ? { id: c.id } : {}),
                label: c.name, type: (c.type || 'Indoor').toLowerCase(),
                schedules: c.schedules.map(s => ({ day: s.day, start: s.start, end: s.end, price: Number(s.price) })),
            })),
        };

        const url = state.id ? `/api/admin/venue_save.php?id=${state.id}` : '/api/admin/venue_save.php';
        const method = state.id ? 'PUT' : 'POST';
        const btn = document.getElementById('btn-save-venue');
        btn.disabled = true; btn.innerText = 'Menyimpan...';
        try {
            const res = await fetch(url, {
                method, credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(bundle),
            });
            if (res.ok) {
                await res.json();
                msg('Tersimpan.');
                await loadMyVenue();
            } else {
                const err = await res.json().catch(() => ({}));
                msg('Gagal menyimpan: ' + (err.error || res.status), false);
            }
        } catch (err) {
            msg('Gagal menyimpan: koneksi.', false);
        }
        btn.disabled = false; btn.innerText = 'Simpan Perubahan Venue';
    });

    // --- Bookings table ----------------------------------------------------
    const tableBody = document.getElementById('admin-bookings-table');
    let bookingFilter = 'all';

    async function loadBookings() {
        const q = bookingFilter === 'all' ? '' : `?status=${bookingFilter}`;
        const res = await fetch(`/api/admin/bookings.php${q}`, { credentials: 'include' });
        const rows = res.ok ? await res.json() : [];
        if (!rows.length) {
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:16px;">Tidak ada pesanan.</td></tr>`;
            return;
        }
        const badge = (s) => {
            if (s === 'paid') return '<span class="badge badge-success">LUNAS</span>';
            if (s === 'pending') return '<span class="badge badge-pending">PENDING</span>';
            return `<span class="badge badge-danger">${s.toUpperCase()}</span>`;
        };
        tableBody.innerHTML = rows.map(b => `
            <tr>
                <td><strong>${b.code || b.id}</strong></td>
                <td>${b.user_name || '-'}</td>
                <td>${b.court_label}</td>
                <td>${badge(b.status)}</td>
                <td>${(b.status === 'pending' || b.status === 'paid')
                    ? `<button class="btn-cancel" data-id="${b.id}" type="button">Batalkan</button>` : '-'}</td>
            </tr>`).join('');
    }

    tableBody.addEventListener('click', async (e) => {
        if (!e.target.classList.contains('btn-cancel')) return;
        if (!confirm('Batalkan pesanan ini? Jika sudah dibayar akan direfund.')) return;
        const id = e.target.dataset.id;
        const res = await fetch(`/api/admin/bookings.php?id=${id}`, { method: 'DELETE', credentials: 'include' });
        if (res.ok) loadBookings(); else alert('Gagal membatalkan.');
    });

    document.querySelectorAll('.admin-bookings-section .tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.admin-bookings-section .tab-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            bookingFilter = e.target.getAttribute('data-filter');
            loadBookings();
        });
    });

    // --- Init --------------------------------------------------------------
    loadMyVenue();
    loadBookings();
});
