@extends('layouts.app')

@section('title', 'History Kelembapan')
@section('page-title', 'History Kelembapan')
@section('page-subtitle', 'Data kelembapan & pH tanah langsung dari sensor IoT')

@section('page-actions')
<div style="display:flex; gap:10px; align-items:center;">
    <select id="sensor-select" onchange="changeSensor(this.value)"
        style="padding:10px 16px; border-radius:999px; border:1px solid #E5E0D5; font-family:inherit; font-size:14px; background:#fff; cursor:pointer;">
        @foreach($sensors as $s)
            <option value="{{ $s->id_sensor }}">{{ $s->nama_sensor }}</option>
        @endforeach
    </select>
    <select id="limit-select" onchange="changeLimit(this.value)"
        style="padding:10px 16px; border-radius:999px; border:1px solid #E5E0D5; font-family:inherit; font-size:14px; background:#fff; cursor:pointer;">
        <option value="30">30 data</option>
        <option value="50">50 data</option>
        <option value="100">100 data</option>
    </select>
</div>
@endsection

@push('styles')
<style>
    /* ── Live bar ── */
    .live-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #0D0D0D;
        color: #fff;
        padding: 10px 18px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 24px;
        width: fit-content;
    }
    .live-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: var(--accent);
        animation: live-pulse 1.5s ease-in-out infinite;
    }
    @keyframes live-pulse {
        0%,100% { opacity:1; transform:scale(1); }
        50%      { opacity:.4; transform:scale(.8); }
    }

    /* ── Summary cards ── */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 24px;
    }
    .summary-card {
        background: #fff;
        border: 1px solid #E5E0D5;
        border-radius: 16px;
        padding: 18px 20px;
    }
    .summary-label {
        font-size: 12px;
        font-weight: 700;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }
    .summary-val {
        font-size: 32px;
        font-weight: 800;
        font-family: 'Sora', sans-serif;
        color: var(--text);
    }
    .summary-val.val-dry  { color: #D97706; }
    .summary-val.val-ok   { color: #65A30D; }
    .summary-val.val-wet  { color: #0284C7; }
    .summary-sub { font-size: 12px; color: #aaa; margin-top: 4px; }

    /* ── Chart card ── */
    .chart-card {
        background: #fff;
        border: 1px solid #E5E0D5;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
        overflow: hidden;
    }
    .chart-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 16px;
        font-family: 'Sora', sans-serif;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .chart-tabs {
        display: flex;
        gap: 8px;
    }
    .chart-tab {
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        border: none;
        cursor: pointer;
        font-family: inherit;
        background: #F5F0E8;
        color: #888;
        transition: all 0.2s;
    }
    .chart-tab.active {
        background: #0D0D0D;
        color: #fff;
    }
    canvas { max-height: 220px; }

    /* ── Table card ── */
    .data-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #E5E0D5;
        overflow: hidden;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 560px;
    }
    .data-table thead tr {
        background: #F0EBE0;
    }
    .data-table th {
        padding: 14px 20px;
        text-align: left;
        font-weight: 700;
        color: var(--text);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #E5E0D5;
    }
    .data-table td {
        padding: 14px 20px;
        font-size: 14px;
        color: #555;
        border-bottom: 1px solid #F5F0E8;
        vertical-align: middle;
    }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: #FAFAF8; }
    .data-table tr.new-row td {
        animation: row-highlight 1.5s ease forwards;
    }
    @keyframes row-highlight {
        0%   { background: rgba(200,241,53,0.2); }
        100% { background: transparent; }
    }

    .badge-kondisi {
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .cond-kering  { background: rgba(217,119,6,.1); color: #D97706; }
    .cond-lembap  { background: rgba(101,163,13,.1); color: #65A30D; }
    .cond-basah   { background: rgba(2,132,199,.1);  color: #0284C7; }
    .cond-unknown { background: #E5E0D5; color: #888; }

    .val-strong {
        font-weight: 700;
        font-family: 'Sora', sans-serif;
    }
    .table-wrap { overflow-x: auto; }

    .empty-row td {
        text-align: center;
        padding: 60px 20px;
        color: #aaa;
        font-size: 15px;
    }

    @media (max-width: 600px) {
        .summary-grid { grid-template-columns: 1fr 1fr; }
        .chart-tabs { flex-wrap: wrap; }
    }
</style>
@endpush

@section('content')

<div class="live-bar">
    <div class="live-dot"></div>
    <span>Live dari IoT</span>
    <span id="poll-status" style="color:#888; font-weight:400;">— memuat...</span>
</div>

{{-- Summary stats --}}
<div class="summary-grid">
    <div class="summary-card">
        <div class="summary-label">Kelembapan Terkini</div>
        <div class="summary-val" id="stat-kel">—</div>
        <div class="summary-sub" id="stat-kondisi">memuat...</div>
    </div>
    <div class="summary-card">
        <div class="summary-label">pH Tanah</div>
        <div class="summary-val" id="stat-ph">—</div>
        <div class="summary-sub">terbaru</div>
    </div>
    <div class="summary-card">
        <div class="summary-label">Rata-rata Kelembapan</div>
        <div class="summary-val" id="stat-avg">—</div>
        <div class="summary-sub" id="stat-period">dari data terbaru</div>
    </div>
    <div class="summary-card">
        <div class="summary-label">Status Pompa</div>
        <div class="summary-val" id="stat-pump" style="font-size:22px;">—</div>
        <div class="summary-sub" id="stat-mode">memuat...</div>
    </div>
</div>

{{-- Chart --}}
<div class="chart-card">
    <div class="chart-title">
        Grafik Kelembapan
        <div class="chart-tabs">
            <button class="chart-tab active" onclick="showChart('kel', this)">Kelembapan</button>
            <button class="chart-tab" onclick="showChart('ph', this)">pH Tanah</button>
        </div>
    </div>
    <canvas id="chartKel"></canvas>
    <canvas id="chartPh" style="display:none;"></canvas>
</div>

{{-- Table --}}
<div class="data-card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Kelembapan</th>
                    <th>pH Tanah</th>
                    <th>Kondisi</th>
                </tr>
            </thead>
            <tbody id="history-tbody">
                <tr><td colspan="4" style="text-align:center; padding:40px; color:#aaa;">Memuat data dari sensor...</td></tr>
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// ════════════════════════════════════════════════════════════
// STATE
// ════════════════════════════════════════════════════════════
let currentSensorId = {{ $sensors->first()->id_sensor ?? 'null' }};
let currentLimit    = 30;
let chartKel        = null;
let chartPh         = null;
let lastRowCount    = 0;
let POLL_INTERVAL   = 7000;

// ════════════════════════════════════════════════════════════
// CHART INIT
// ════════════════════════════════════════════════════════════
function initCharts(labels, kelData, phData) {
    const sharedOptions = {
        responsive: true,
        animation: { duration: 400 },
        plugins: {
            legend: { display: false },
            tooltip: { mode: 'index', intersect: false },
        },
        scales: {
            x: {
                ticks: { maxTicksLimit: 8, font: { size: 11 } },
                grid: { display: false },
            },
            y: { grid: { color: '#F0EBE0' }, ticks: { font: { size: 11 } } }
        },
    };

    // Kelembapan chart
    if (chartKel) chartKel.destroy();
    chartKel = new Chart(document.getElementById('chartKel'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data: kelData,
                borderColor: '#0D0D0D',
                backgroundColor: 'rgba(200,241,53,0.15)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#0D0D0D',
                fill: true,
                tension: 0.4,
            }]
        },
        options: { ...sharedOptions, scales: { ...sharedOptions.scales, y: { ...sharedOptions.scales.y, min: 0, max: 100, ticks: { callback: v => v + '%', font: { size: 11 } } } } }
    });

    // pH chart
    if (chartPh) chartPh.destroy();
    chartPh = new Chart(document.getElementById('chartPh'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data: phData,
                borderColor: '#0284C7',
                backgroundColor: 'rgba(2,132,199,0.1)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#0284C7',
                fill: true,
                tension: 0.4,
            }]
        },
        options: { ...sharedOptions, scales: { ...sharedOptions.scales, y: { ...sharedOptions.scales.y, min: 0, max: 14, ticks: { font: { size: 11 } } } } }
    });
}

function showChart(type, btn) {
    document.querySelectorAll('.chart-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('chartKel').style.display = type === 'kel' ? 'block' : 'none';
    document.getElementById('chartPh').style.display  = type === 'ph'  ? 'block' : 'none';
}

// ════════════════════════════════════════════════════════════
// FETCH HISTORY
// ════════════════════════════════════════════════════════════
async function fetchHistory() {
    if (!currentSensorId) return;

    try {
        const res  = await fetch(`/api/v1/sensors/${currentSensorId}/history?limit=${currentLimit}`, {
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();

        if (json.status !== 'success') return;

        const rows = json.data;
        updateTable(rows);
        updateCharts(rows);
        updateSummary(rows);

        const now = new Date();
        document.getElementById('poll-status').textContent =
            '— ' + now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    } catch (err) {
        console.warn('[MAPIA History] Gagal:', err.message);
    }
}

// ════════════════════════════════════════════════════════════
// FETCH LIVE (untuk summary pompa & mode)
// ════════════════════════════════════════════════════════════
async function fetchLive() {
    if (!currentSensorId) return;
    try {
        const res  = await fetch(`/api/v1/sensors/${currentSensorId}/live`, {
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (json.status !== 'success') return;

        const d = json.data;
        const pumpEl = document.getElementById('stat-pump');
        const modeEl = document.getElementById('stat-mode');
        if (pumpEl) {
            pumpEl.textContent = d.pump_on ? '💧 ON' : '○ OFF';
            pumpEl.style.color = d.pump_on ? '#65A30D' : '#888';
        }
        if (modeEl) modeEl.textContent = 'Mode: ' + (d.mode_auto ? 'Otomatis' : 'Manual');
    } catch (err) {}
}

// ════════════════════════════════════════════════════════════
// UPDATE TABLE
// ════════════════════════════════════════════════════════════
function updateTable(rows) {
    const tbody = document.getElementById('history-tbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="empty-row" style="text-align:center; padding:60px; color:#aaa;">Belum ada data history kelembapan.</td></tr>`;
        return;
    }

    const newCount = rows.length;
    const isNew    = newCount > lastRowCount && lastRowCount > 0;

    tbody.innerHTML = [...rows].reverse().map((r, idx) => {
        const kondisiClass = {
            'KERING': 'cond-kering',
            'LEMBAP': 'cond-lembap',
            'BASAH':  'cond-basah',
        }[r.kondisi] || 'cond-unknown';

        const kelClass = r.kelembapan < 30 ? 'val-dry' : (r.kelembapan > 70 ? 'val-wet' : 'val-ok');
        const rowClass = (isNew && idx === 0) ? 'new-row' : '';

        return `<tr class="${rowClass}">
            <td>${r.tanggal}</td>
            <td><span class="val-strong ${kelClass}">${r.kelembapan}%</span></td>
            <td><span class="val-strong">${r.ph_tanah.toFixed(2)}</span></td>
            <td><span class="badge-kondisi ${kondisiClass}">${r.kondisi}</span></td>
        </tr>`;
    }).join('');

    lastRowCount = newCount;
}

// ════════════════════════════════════════════════════════════
// UPDATE CHARTS
// ════════════════════════════════════════════════════════════
function updateCharts(rows) {
    const labels  = rows.map(r => r.waktu);
    const kelData = rows.map(r => r.kelembapan);
    const phData  = rows.map(r => r.ph_tanah);

    if (!chartKel) {
        initCharts(labels, kelData, phData);
    } else {
        chartKel.data.labels   = labels;
        chartKel.data.datasets[0].data = kelData;
        chartKel.update('none');

        chartPh.data.labels   = labels;
        chartPh.data.datasets[0].data = phData;
        chartPh.update('none');
    }
}

// ════════════════════════════════════════════════════════════
// UPDATE SUMMARY
// ════════════════════════════════════════════════════════════
function updateSummary(rows) {
    if (!rows || rows.length === 0) return;

    const latest = rows[rows.length - 1];
    const kelNow = latest.kelembapan;
    const avg    = (rows.reduce((s, r) => s + r.kelembapan, 0) / rows.length).toFixed(1);

    const kelEl = document.getElementById('stat-kel');
    if (kelEl) {
        kelEl.textContent = kelNow + '%';
        kelEl.className   = 'summary-val ' + (kelNow < 30 ? 'val-dry' : (kelNow > 70 ? 'val-wet' : 'val-ok'));
    }

    const condEl = document.getElementById('stat-kondisi');
    if (condEl) condEl.textContent = latest.kondisi;

    const phEl = document.getElementById('stat-ph');
    if (phEl) phEl.textContent = latest.ph_tanah.toFixed(2);

    const avgEl = document.getElementById('stat-avg');
    if (avgEl) avgEl.textContent = avg + '%';

    const periodEl = document.getElementById('stat-period');
    if (periodEl) periodEl.textContent = `dari ${rows.length} data terakhir`;
}

// ════════════════════════════════════════════════════════════
// CONTROLS
// ════════════════════════════════════════════════════════════
function changeSensor(id) {
    currentSensorId = id;
    lastRowCount    = 0;
    fetchHistory();
    fetchLive();
}

function changeLimit(val) {
    currentLimit = parseInt(val);
    fetchHistory();
}

// ════════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════════
fetchHistory();
fetchLive();
setInterval(fetchHistory, POLL_INTERVAL);
setInterval(fetchLive, 5000);
</script>
@endpush