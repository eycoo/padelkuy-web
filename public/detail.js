document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('detail-container');
    if (!container) return;

    // Mock data (Sama seperti sebelumnya)
    const mockVenueData = {
        name: "The G Club Padel",
        address: "Jl. Senopati No. 42, Jakarta Selatan",
        description: "A fully enclosed glass-walled padel court with a professional-grade synthetic turf. Air conditioned, well-lit for night sessions, and just a short walk from the main lobby.",
        facilities: ["Racket", "Shower", "Shop", "Snack", "Towel", "Locker", "Cafe"],
        courts: [
            {
                id: "c1", name: "Lapangan 1", desc: "Indoor VIP",
                slots: [
                    { time: "07:00-08:00", status: "booked", price: 150000 },
                    { time: "08:00-09:00", status: "available", price: 150000 },
                    { time: "09:00-10:00", status: "available", price: 150000 },
                    { time: "10:00-11:00", status: "booked", price: 150000 },
                    { time: "11:00-12:00", status: "booked", price: 150000 },
                    { time: "12:00-13:00", status: "available", price: 120000 },
                    { time: "13:00-14:00", status: "available", price: 120000 },
                    { time: "14:00-15:00", status: "booked", price: 120000 },
                ]
            },
            {
                id: "c2", name: "Lapangan 2", desc: "Semi-Outdoor Rooftop",
                slots: [
                    { time: "07:00-08:00", status: "booked", price: 140000 },
                    { time: "08:00-09:00", status: "booked", price: 140000 },
                    { time: "09:00-10:00", status: "available", price: 140000 },
                    { time: "10:00-11:00", status: "available", price: 140000 },
                ]
            }
        ]
    };

    // Variabel Kalender
    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    let todayDate = new Date();
    let currentMonth = todayDate.getMonth();
    let currentYear = todayDate.getFullYear();
    
    // Default terpilih hari ini
    let selectedDate = todayDate.getDate();
    let selectedMonth = currentMonth;
    let selectedYear = currentYear;

    const renderDetail = () => {
        const svgIcon = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>`;
        
        const facHTML = mockVenueData.facilities.map(f => `
            <div class="facility-item"><div class="facility-icon">${svgIcon}</div><span>${f}</span></div>
        `).join('');

        const courtHTML = mockVenueData.courts.map(c => `
            <div class="court-item" data-court="${c.id}">
                <div class="court-header">
                    <h4>${c.name} <span>${c.desc}</span></h4>
                    <button class="btn-cek-jadwal">Cek Jadwal</button>
                </div>
                <div class="court-body-expand">
                    <div class="court-slots">
                        ${c.slots.map(s => `
                            <div class="slot-btn ${s.status === 'booked' ? 'booked' : ''}" data-time="${s.time}">
                                <span class="slot-time">${s.time}</span>
                                <span class="slot-price">${s.status === 'booked' ? 'Booked' : 'Rp ' + (s.price/1000) + 'k'}</span>
                            </div>
                        `).join('')}
                    </div>
                    <div class="court-action">
                        <button class="btn-konfirmasi" disabled>Konfirmasi & Bayar</button>
                    </div>
                </div>
            </div>
        `).join('');

        // Render Base HTML
        container.innerHTML = `
            <div class="venue-info">
                <div class="venue-header">
                    <h1>${mockVenueData.name}</h1>
                    <p class="venue-address">${mockVenueData.address}</p>
                    <div class="venue-gallery">
                        <img src="assets/images/court-1-image.jpeg" alt="Main" class="gallery-img img-main" onerror="this.src=''">
                        <img src="assets/images/court-2-image.jpeg" alt="Side 1" class="gallery-img" onerror="this.src=''">
                        <img src="assets/images/court-3-image.jpeg" alt="Side 2" class="gallery-img" onerror="this.src=''">
                    </div>
                </div>
                <div class="venue-desc">
                    <h3>About this court</h3>
                    <p>${mockVenueData.description}</p>
                    <div class="facilities-wrap">
                        <h3 style="margin-top: 24px;">Facility</h3>
                        <div class="facilities">${facHTML}</div>
                    </div>
                </div>
            </div>
            
            <div class="booking-section">
                <div class="booking-header">
                    <h2>Mau main hari apa?</h2>
                    <h3 class="selected-date-label" id="selected-date-text">${selectedDate} ${monthNames[selectedMonth]} ${selectedYear}</h3>
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
                    <div class="court-list">${courtHTML}</div>
                </div>
            </div>
        `;
    };

    // Fungsi Render Kalender Grid Dinamis
    const renderInlineCalendar = (month, year) => {
        document.getElementById('detail-month-year').innerText = `${monthNames[month]} ${year}`;
        const grid = document.getElementById('detail-calendar-grid');
        
        let daysHTML = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].map(d => `<div class="day-label">${d}</div>`).join('');
        
        const firstDay = new Date(year, month, 1).getDay(); 
        const daysInMonth = new Date(year, month + 1, 0).getDate(); 
        let today = new Date();
        today.setHours(0,0,0,0); 

        // Kotak kosong awal bulan
        for (let i = 0; i < firstDay; i++) {
            daysHTML += `<div class="date-cell past empty"></div>`;
        }

        // Kotak isi tanggal
        for (let i = 1; i <= daysInMonth; i++) {
            let loopDate = new Date(year, month, i);
            let isPast = loopDate < today ? 'past' : '';
            let isSelected = (i === selectedDate && month === selectedMonth && year === selectedYear) ? 'selected' : '';
            
            daysHTML += `<div class="date-cell ${isPast} ${isSelected}">${i}</div>`;
        }
        grid.innerHTML = daysHTML;
    };

    // Jalankan Render
    renderDetail();
    renderInlineCalendar(currentMonth, currentYear);

    // Event Delegation (Menangani semua klik)
    container.addEventListener('click', (e) => {
        
        // --- Logika Akordion & Tombol Slot ---
        if (e.target.classList.contains('btn-cek-jadwal')) {
            e.target.closest('.court-item').classList.toggle('expanded');
        }
        
        const slotBtn = e.target.closest('.slot-btn');
        if (slotBtn && !slotBtn.classList.contains('booked')) {
            const courtItem = slotBtn.closest('.court-item');
            courtItem.querySelectorAll('.slot-btn').forEach(btn => btn.classList.remove('selected'));
            slotBtn.classList.add('selected');
            courtItem.querySelector('.btn-konfirmasi').removeAttribute('disabled');
        }

        if (e.target.classList.contains('btn-konfirmasi')) {
            window.location.href = 'pembayaran.html'; 
        }

        // --- Logika Navigasi Kalender (Bulan Kiri Kanan) ---
        if (e.target.id === 'detail-prev-month') {
            currentMonth--;
            if (currentMonth < 0) { currentMonth = 11; currentYear--; }
            renderInlineCalendar(currentMonth, currentYear);
        }
        
        if (e.target.id === 'detail-next-month') {
            currentMonth++;
            if (currentMonth > 11) { currentMonth = 0; currentYear++; }
            renderInlineCalendar(currentMonth, currentYear);
        }

        // --- Logika Pemilihan Tanggal Kalender ---
        if (e.target.classList.contains('date-cell') && !e.target.classList.contains('past') && !e.target.classList.contains('empty')) {
            // Update state variabel
            selectedDate = parseInt(e.target.innerText);
            selectedMonth = currentMonth;
            selectedYear = currentYear;
            
            // Re-render grid agar warnanya ter-update
            renderInlineCalendar(currentMonth, currentYear);
            
            // Update text di atas jadwal
            document.getElementById('selected-date-text').innerText = `${selectedDate} ${monthNames[selectedMonth]} ${selectedYear}`;
            
            // Note: Nanti di sini kamu bisa menambahkan fungsi fetch() ulang ke API jika tanggal berubah!
        }
    });
});