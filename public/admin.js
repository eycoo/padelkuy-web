document.addEventListener('DOMContentLoaded', async () => {
    
    // Auth Check
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const userName = localStorage.getItem('userName') || 'Admin';
    const userRole = localStorage.getItem('userRole'); 
    const venueName = localStorage.getItem('adminVenueName') || '';

    // if (!isLoggedIn || userRole !== 'admin') {
    //     alert("Akses Ditolak. Anda harus login sebagai Admin.");
    //     window.location.href = 'login.html';
    //     return;
    // }

    document.getElementById('admin-name').innerText = userName;
    if (venueName) {
        document.getElementById('venue-name').value = venueName;
    }

    // Logout Action
    document.getElementById('btn-admin-logout').addEventListener('click', async () => {
        try {
            await fetch('/api/logout.php', { method: 'POST', credentials: 'include' });
        } catch(e) {}
        localStorage.clear();
        window.location.href = 'login.html';
    });

    // Input dinamis untuk chips fasilitas
    const facilityInput = document.getElementById('facility-input');
    const facilityChips = document.getElementById('facility-chips');

    if (facilityInput) {
        facilityInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && facilityInput.value.trim() !== '') {
                const val = facilityInput.value.trim();
                const span = document.createElement('span');
                span.className = 'chip';
                span.innerHTML = `${val} <button class="btn-remove-chip" onclick="this.parentElement.remove()">x</button>`;
                facilityChips.appendChild(span);
                facilityInput.value = '';
            }
        });
    }

    // Manajemen data lapangan dan jadwal
    const courtsWrapper = document.getElementById('courts-wrapper');
    const btnAddCourt = document.getElementById('btn-add-court');

    // Data statis awal
    let courtsData = [
        {
            id: 1, 
            name: 'Lapangan 1', 
            type: 'Indoor',
            schedules: [
                { day: 'EVERYDAY', start: '07:00', end: '08:00', price: 150000 },
                { day: 'SAT-SUN', start: '15:00', end: '16:00', price: 150000 }
            ]
        }
    ];

    // Merender array ke dalam HTML
    function renderCourts() {
        if (!courtsWrapper) return;
        courtsWrapper.innerHTML = courtsData.map((c, i) => `
            <div class="court-card">
                <div class="court-header">
                    <div>
                        <div class="editable-text" contenteditable="true" style="font-weight: bold; font-size: 16px;" onblur="updateCourt(${i}, 'name', this.innerText)">${c.name}</div>
                        <div class="editable-text text-muted" contenteditable="true" style="font-size: 13px;" onblur="updateCourt(${i}, 'type', this.innerText)">${c.type}</div>
                    </div>
                    <button class="btn-cancel" onclick="removeCourt(${i})">Hapus Lapangan</button>
                </div>
                <div class="schedules-container">
                    ${c.schedules.map((s, j) => `
                        <div class="schedule-row">
                            <select class="schedule-input" onchange="updateSchedule(${i}, ${j}, 'day', this.value)">
                                <option value="EVERYDAY" ${s.day === 'EVERYDAY' ? 'selected' : ''}>EVERYDAY</option>
                                <option value="MON-FRI" ${s.day === 'MON-FRI' ? 'selected' : ''}>MON-FRI</option>
                                <option value="SAT-SUN" ${s.day === 'SAT-SUN' ? 'selected' : ''}>SAT-SUN</option>
                            </select>
                            <input type="time" class="schedule-input" value="${s.start}" onchange="updateSchedule(${i}, ${j}, 'start', this.value)">
                            <input type="time" class="schedule-input" value="${s.end}" onchange="updateSchedule(${i}, ${j}, 'end', this.value)">
                            <input type="number" class="schedule-input" value="${s.price}" placeholder="Harga (mis: 150000)" oninput="updateSchedule(${i}, ${j}, 'price', this.value)">
                            <button class="btn-cancel" style="padding: 6px;" onclick="removeSchedule(${i}, ${j})">x</button>
                        </div>
                    `).join('')}
                </div>
                <button class="btn-ghost" style="width: 100%; border: 1px dashed var(--line); margin-top: 8px; padding: 6px;" onclick="addSchedule(${i})">+ Tambah Jadwal</button>
            </div>
        `).join('');
    }

    // Fungsi global agar bisa dipanggil lewat onclick HTML
    window.updateCourt = (index, key, value) => { courtsData[index][key] = value; };
    window.removeCourt = (index) => { courtsData.splice(index, 1); renderCourts(); };
    window.removeSchedule = (courtIdx, schedIdx) => { courtsData[courtIdx].schedules.splice(schedIdx, 1); renderCourts(); };
    window.addSchedule = (courtIdx) => { 
        courtsData[courtIdx].schedules.push({day: 'EVERYDAY', start: '00:00', end: '00:00', price: 0}); 
        renderCourts(); 
    };

    if (btnAddCourt) {
        btnAddCourt.addEventListener('click', () => {
            courtsData.push({ id: Date.now(), name: 'Lapangan Baru', type: 'Indoor', schedules: [] });
            renderCourts();
        });
    }
    window.updateSchedule = (courtIdx, schedIdx, key, value) => { courtsData[courtIdx].schedules[schedIdx][key] = value; };

    renderCourts();

    // Mock Bookings Table
    const tableBody = document.getElementById('admin-bookings-table');
    const mockBookings = [
        { id: "PK-98273", name: "Andi S.", court: "Lapangan 1", status: "paid" },
        { id: "PK-98274", name: "Dwi", court: "Lapangan 1", status: "paid" },
        { id: "PK-98275", name: "Andi S.", court: "Lapangan 1", status: "pending" },
        { id: "PK-98276", name: "Budi", court: "Lapangan 2", status: "pending" }
    ];

    const renderTable = (filterStatus = 'all') => {
        const filtered = mockBookings.filter(b => {
            if (filterStatus === 'all') return true;
            if (filterStatus === 'completed') return b.status === 'paid';
            return b.status === filterStatus;
        });

        tableBody.innerHTML = filtered.map(b => {
            let badgeHtml = b.status === 'paid' 
                ? `<span class="badge badge-success">LUNAS</span>`
                : `<span class="badge badge-pending">PENDING</span>`;

            return `
                <tr>
                    <td><strong>#${b.id}</strong></td>
                    <td>${b.name}</td>
                    <td>${b.court}</td>
                    <td>${badgeHtml}</td>
                    <td>
                        <button class="btn-cancel" onclick="alert('Batalkan pesanan ${b.id}?')">Batalkan</button>
                    </td>
                </tr>
            `;
        }).join('');
    };

    renderTable();

    // Tab Filter Logic
    const tabBtns = document.querySelectorAll('.admin-bookings-section .tab-btn');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            tabBtns.forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            renderTable(e.target.getAttribute('data-filter'));
        });
    });
});