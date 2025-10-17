// Statistics modal open
function openStatisticsModal() {
    new bootstrap.Modal(document.getElementById('statisticsModal')).show();
    loadStatistics();
}

// Load Statistics (use internal endpoint only)
function loadStatistics() {
    fetch('workpoint_supervisor_dashboard.php?action=workpoint_stats')
        .then(r => r.json())
        .then(res => {
            if (!res || !res.success) throw new Error(res && res.message ? res.message : 'Failed to load stats');
            const data = res.data || {};
            displaySpecialistStats(data);
            displayBookingStats(data);
        })
        .catch(error => {
            console.error('Error:', error);
            if (document.getElementById('specialistStats'))
                document.getElementById('specialistStats').innerHTML = '<div class="text-center text-danger">Error loading statistics</div>';
            if (document.getElementById('bookingStats'))
                document.getElementById('bookingStats').innerHTML = '<div class="text-center text-danger">Error loading statistics</div>';
        });
}

// Display Specialist Statistics (internal data)
function displaySpecialistStats(data) {
    const specialistStats = document.getElementById('specialistStats');
    const top = data.topSpecialist;
    specialistStats.innerHTML = `
        <div class="border rounded p-2 text-center">
            ${top ? (`<div><strong>Most wanted specialist (30 days):</strong></div>
                      <div>${top.name} <span class=\"text-muted\">(${top.bookings} bookings)</span></div>`) : '<div class="text-muted">No booking data in last 30 days.</div>'}
        </div>
        <div class="mt-3">
            <small class="text-muted">Last updated: ${new Date().toLocaleString()}</small>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                <h6><i class="fas fa-robot"></i> Ask AI to make a statistic for you</h6>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">Describe what you want to analyze and we will generate a summary.</p>
                <div class="input-group">
                    <input type="text" class="form-control" id="aiQuery" placeholder="e.g., busiest hours last month, top 3 specialists this week">
                    <button class="btn btn-primary" type="button" onclick="askAiForStats()">Ask AI</button>
                </div>
            </div>
        </div>
    `;
}

// Display Booking Statistics (internal data)
function displayBookingStats(data) {
    const bookingStats = document.getElementById('bookingStats');
    const busiest = data.busiest;
    const relaxed = data.relaxed;
    bookingStats.innerHTML = `
        <div class="row text-center">
            <div class="col-6">
                <div class="border rounded p-2">
                    <h4 class="text-info">${data.activeFuture || 0}</h4>
                    <small>Active future bookings</small>
                </div>
            </div>
            <div class="col-6">
                <div class="border rounded p-2">
                    <h6 class="mb-1">Busiest / Most Relaxed</h6>
                    <small>${busiest ? `${busiest.day} (${busiest.count})` : 'N/A'} / ${relaxed ? `${relaxed.day} (${relaxed.count})` : 'N/A'}</small>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <small class="text-muted">Last updated: ${new Date().toLocaleString()}</small>
        </div>
        <canvas id="perMonthChart" height="140" style="width:100%;"></canvas>
    `;

    if (Array.isArray(data.perMonth) && data.perMonth.length) {
        drawSimpleBarChart(document.getElementById('perMonthChart'), data.perMonth);
    }
}

function askAiForStats() {
    const q = (document.getElementById('aiQuery')?.value || '').trim();
    if (!q) { alert('Please enter a question.'); return; }
    alert('AI will analyze: ' + q + '\n(This is a placeholder for AI integration.)');
}

// Month chart drawer with formatted labels (Aug-25 etc.)
function drawSimpleBarChart(canvas, series) {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const width = canvas.width = canvas.offsetWidth;
    const height = canvas.height; // keep set
    ctx.clearRect(0,0,width,height);
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const labels = series.map(s => {
        const [y,m] = (s.month || '').split('-').map(x=>parseInt(x,10));
        const mon = monthNames[(m||1)-1] || '';
        const yy = (y||0) % 100;
        return `${mon}-${yy.toString().padStart(2,'0')}`;
    });
    const values = series.map(s => s.count);
    const max = Math.max(1, ...values);
    const padding = 24;
    const chartW = width - padding*2;
    const chartH = height - padding*2;
    const barCount = values.length;
    const barW = chartW / Math.max(1, barCount);
    ctx.fillStyle = '#f5f5f5';
    ctx.fillRect(padding, padding, chartW, chartH);
    ctx.strokeStyle = '#999';
    ctx.beginPath();
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, padding+chartH);
    ctx.lineTo(padding+chartW, padding+chartH);
    ctx.stroke();
    ctx.fillStyle = '#00adb5';
    values.forEach((v, i) => {
        const h = (v / max) * (chartH - 10);
        const x = padding + i*barW + barW*0.15;
        const y = padding + chartH - h;
        const w = barW*0.7;
        ctx.fillRect(x, y, w, h);
    });
    ctx.fillStyle = '#333';
    ctx.font = '10px sans-serif';
    labels.forEach((lab, i) => {
        const x = padding + i*barW + barW*0.15;
        const y = padding + chartH + 12;
        ctx.fillText(lab, x, y);
    });
}


