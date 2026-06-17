document.addEventListener('DOMContentLoaded', () => {
    const dateWrapper = document.getElementById('date-picker-wrapper');
    const dateDisplay = document.getElementById('date-display');
    const calendarPopup = document.getElementById('custom-calendar-popup');
    const hiddenInput = document.getElementById('selected-date');
    const monthYearDisplay = document.getElementById('kalender-bulan-tahun');
    const calendarGrid = document.getElementById('kalender-grid');

    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    
    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();

    dateDisplay.addEventListener('click', () => {
        calendarPopup.classList.toggle('active');
    });

    document.addEventListener('click', (e) => {
        if (!dateWrapper.contains(e.target)) {
            calendarPopup.classList.remove('active');
        }
    });

    function renderCalendar(month, year) {
        // Ubah teks judul bulan dan tahun
        monthYearDisplay.innerText = `${monthNames[month]} ${year}`;
        
        // Reset isi grid kalender (sisakan label hari)
        let daysHTML = `
            <div class="day-label">Su</div>
            <div class="day-label">Mo</div>
            <div class="day-label">Tu</div>
            <div class="day-label">We</div>
            <div class="day-label">Th</div>
            <div class="day-label">Fr</div>
            <div class="day-label">Sa</div>
        `;

        const firstDay = new Date(year, month, 1).getDay(); 
        const daysInMonth = new Date(year, month + 1, 0).getDate(); 
        
        let today = new Date();
        today.setHours(0,0,0,0); 

        for (let i = 0; i < firstDay; i++) {
            daysHTML += `<span class="date-cell muted"></span>`;
        }

        for (let i = 1; i <= daysInMonth; i++) {
            let loopDate = new Date(year, month, i);
            
            if (loopDate < today) {
                daysHTML += `<span class="date-cell past">${i}</span>`;
            } else {
                daysHTML += `<a href="javascript:void(0)" class="date-cell">${i}</a>`;
            }
        }

        calendarGrid.innerHTML = daysHTML;
    }

    renderCalendar(currentMonth, currentYear);

    document.getElementById('prev-month').addEventListener('click', () => {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        renderCalendar(currentMonth, currentYear);
    });

    document.getElementById('next-month').addEventListener('click', () => {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        renderCalendar(currentMonth, currentYear);
    });


    calendarPopup.addEventListener('click', (e) => {
        if (e.target.classList.contains('date-cell') && !e.target.classList.contains('muted') && !e.target.classList.contains('past')) {
            const selectedDate = e.target.innerText;
            
            dateDisplay.innerText = `${selectedDate} ${monthNames[currentMonth]} ${currentYear}`;
            dateDisplay.classList.add('has-value');
            
            hiddenInput.value = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${selectedDate.padStart(2, '0')}`;
            
            calendarPopup.classList.remove('active');

            document.querySelectorAll('#kalender-grid .date-cell').forEach(el => el.classList.remove('today'));
            e.target.classList.add('today');
        }
    });

});

// venue render API & Auth State
document.addEventListener('DOMContentLoaded', () => {
    const venueGrid = document.querySelector('.venue-grid');
    const cityFilter = document.getElementById('city-filter');
    const searchBtn = document.getElementById('search-btn');
    const navActions = document.getElementById('nav-actions');

    // Cek status login statis untuk testing
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true'; 
    const userName = localStorage.getItem('userName') || 'Demo_user';

    if (navActions) {
        // SVG Ikon Tiket 
        const ticketIconSVG = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="10" rx="2" ry="2"></rect><path d="M12 7v10"></path><path d="M6 7v10"></path><path d="M18 7v10"></path></svg>`;

        if (isLoggedIn) {
            // Render jika sudah login (Nama Profil & Tiket dengan Notif)
           navActions.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="profile-name">${userName}</span>
                    <button id="btn-logout" class="btn btn-ghost" style="padding: 8px 16px;">Logout</button>
                </div>
                <a href="riwayat.html" class="ticket-icon-wrapper" title="Riwayat Pesanan">
                    ${ticketIconSVG}
                    <span class="notif-badge"></span>
                </a>
            `;
            // Logika fungsi klik untuk Logout
            document.getElementById('btn-logout').addEventListener('click', async () => {
                try {
                    await fetch('/api/logout.php', {
                        method: 'POST',
                        credentials: 'include'
                    });
                } catch (err) {
                    console.error("Gagal logout:", err);
                }
                localStorage.removeItem('isLoggedIn');
                localStorage.removeItem('userName');
                window.location.reload();
            });
        } else {
            // Render jika belum login (Login & Register menjadi Button terpisah)
            navActions.innerHTML = `
                <a href="login.html" class="nav-btn btn-login">Login</a>
                <a href="register.html" class="nav-btn btn-register">Register</a>
                <a href="login.html" class="ticket-icon-wrapper" title="Masuk untuk melihat tiket">
                    ${ticketIconSVG}
                </a>
            `;
        }
    }

    if (!venueGrid) return;

    const fetchVenues = async (city = '') => {
        try {
            const url = city ? `/api/venues.php?city=${city}` : '/api/venues.php';
            const response = await fetch(url);
            const venues = await response.json();
            renderVenues(venues);
        } catch (error) {
            venueGrid.innerHTML = '<p class="empty-state">Gagal memuat data venue dari server.</p>';
        }
    };

    const renderVenues = (venues) => {
        if (venues.length === 0) {
            venueGrid.innerHTML = '<p class="empty-state">Maaf, belum ada venue tersedia di kota ini.</p>';
            return;
        }

        venueGrid.innerHTML = venues.map(v => {
            const priceK = v.price_per_hour / 1000;
            // Pengalihan url pemesanan
            const targetUrl = isLoggedIn ? `detail.html?id=${v.id}` : `login.html`;
            
            return `
            <div class="court-card">
                <div class="court-image">
                    <img src="${v.image_path}" alt="${v.name}">
                </div>
                <div class="court-body">
                    <h3>${v.name}</h3>
                    <span class="court-tag">${v.city}</span>
                    <div class="court-foot">
                        <div class="price">Rp ${priceK}k <small>/ hr</small></div>
                        <a href="${targetUrl}" class="btn btn-primary">Book Now</a>
                    </div>
                </div>
            </div>
            `;
        }).join('');
    };

    searchBtn.addEventListener('click', () => {
        fetchVenues(cityFilter.value);
    });

    fetchVenues();
});