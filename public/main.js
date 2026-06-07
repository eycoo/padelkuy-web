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