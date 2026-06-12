@extends('layouts.app')

@section('title', 'Dashboard Utama')
@section('page-title', 'Dashboard Utama')
@section('page-subtitle', 'Pantauan kebun Anda dalam satu tampilan')

@section('page-actions')
<div class="live-bar-top">
    <div class="live-dot-sm"></div>
    <span id="dash-updated">memuat...</span>
</div>
@endsection

@push('styles')
<style>
    .live-bar-top {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #0D0D0D;
        color: #fff;
        padding: 8px 16px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
    }
    .live-dot-sm {
        width: 7px; height: 7px;
        border-radius: 50%;
        background: var(--accent);
        animation: live-pulse 1.5s ease-in-out infinite;
        flex-shrink: 0;
    }
    #dash-updated { color: #aaa; font-weight: 400; }
    @keyframes live-pulse {
        0%,100% { opacity:1; transform:scale(1); }
        50%      { opacity:.4; transform:scale(.8); }
    }

    /* ── Welcome Banner ── */
    .welcome-banner {
        background: #0D0D0D;
        border: 1px solid #E5E0D5;
        padding: 20px;
        border-radius: 16px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        font-size: 18px;
        font-weight: 600;
        color: #FFFFFF;
        font-family: 'Sora', sans-serif;
    }

    /* ── Stat Cards ── */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }
    .stat-card {
        background: #FFFFFF;
        padding: 20px;
        border-radius: 16px;
        border: 1px solid #E5E0D5;
        transition: all 0.3s ease;
    }
    .stat-card:hover { border-color: var(--text); transform: translateY(-4px); }
    .stat-card .stat-label {
        font-size: 14px; font-weight: 600; color: #666; margin-bottom: 12px;
    }
    .stat-card .stat-value {
        font-size: 40px; font-weight: 800; line-height: 1; font-family: 'Sora', sans-serif;
        transition: color 0.3s;
    }
    .stat-card.primary-card { background: #0D0D0D; }
    .stat-card.primary-card .stat-label { color: #888; }
    .stat-card.primary-card .stat-value { color: #FFFFFF; }

    /* ── Section Headers ── */
    .section-title {
        font-size: 20px; font-weight: 700; color: var(--text);
        margin-bottom: 20px; display: flex; align-items: center; gap: 12px;
    }

    /* ── Sensor Cards ── */
    .sensor-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 16px;
        margin-bottom: 40px;
    }
    .sensor-card {
        background: #FFFFFF;
        border-radius: 16px;
        padding: 20px;
        border: 1px solid #E5E0D5;
        position: relative;
        overflow: hidden;
        transition: all 0.25s ease;
    }
    .sensor-card:hover { border-color: var(--text); }
    .sensor-card::after {
        content: '';
        position: absolute; top: 0; left: 0;
        width: 4px; height: 100%;
        background: var(--text);
    }
    .sensor-card .card-header {
        display: flex; justify-content: space-between;
        align-items: flex-start; margin-bottom: 20px;
    }
    .sensor-card .card-header h3 { font-size: 18px; font-weight: 700; color: var(--text); margin-bottom: 4px; }
    .sensor-card .card-header p { font-size: 13px; color: #888; }
    .badge-status {
        padding: 4px 12px; border-radius: 999px;
        font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        transition: all 0.3s;
    }
    .badge-aktif { background: var(--accent); color: #0D0D0D; }
    .badge-mati  { background: #E5E0D5; color: #666; }

    .sensor-readings { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
    .reading-box { background: #F5F0E8; padding: 16px 12px; border-radius: 12px; text-align: center; }
    .reading-box .reading-value {
        font-size: 26px; font-weight: 800; display: block;
        color: var(--text); margin-bottom: 4px; font-family: 'Sora', sans-serif;
        transition: color 0.3s;
    }
    .reading-box .reading-label { font-size: 12px; color: #666; font-weight: 600; }

    .sensor-footer {
        display: flex; justify-content: space-between; align-items: center;
        padding-top: 16px; border-top: 1px solid #E5E0D5;
    }
    .sensor-footer .time { font-size: 12px; color: #888; }
    .sensor-footer .condition { font-weight: 700; font-size: 13px; }
    .condition-aman   { color: #0D0D0D; }
    .condition-kering { color: #D97706; }
    .condition-basah  { color: #0284C7; }

    /* ── Pump indicator on card ── */
    .pump-badge {
        font-size: 11px; font-weight: 700;
        padding: 3px 10px; border-radius: 999px;
        background: #F5F0E8; color: #888;
        transition: all 0.3s;
    }
    .pump-badge.on { background: var(--accent); color: #0D0D0D; }

    /* ── IoT Signal ── */
    .iot-preview {
        background: #FFFFFF;
        color: var(--text);
        padding: 32px;
        border-radius: 16px;
        border: 1px solid #E5E0D5;
        text-align: center;
        margin-bottom: 32px;
    }
    .iot-preview h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
    .iot-preview p { color: #666; font-size: 14px; }
    .signal-bars { display: flex; justify-content: center; gap: 8px; height: 60px; align-items: flex-end; margin: 20px 0; }
    .signal-bar {
        width: 10px; background: var(--accent); border-radius: 4px;
        animation: signal-pulse 1.2s ease-in-out infinite alternate;
    }
    @keyframes signal-pulse {
        from { opacity: 0.3; transform: scaleY(0.7); }
        to   { opacity: 1;   transform: scaleY(1);   }
    }

    /* ── Empty State ── */
    .empty-state { grid-column: 1/-1; text-align: center; padding: 80px 20px; color: #aaa; }
    .empty-state h2 { font-size: 22px; margin-bottom: 8px; color: #888; }

    @media (max-width: 600px) {
        .stat-grid { grid-template-columns: 1fr 1fr; gap: 16px; }
        .sensor-grid { grid-template-columns: 1fr; }
        .stat-card .stat-value { font-size: 32px; }
    }
</style>
@endpush

@section('content')

{{-- Welcome Banner --}}
<div class="welcome-banner">
    <span>
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"></path><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
    </span>
    Selamat datang, {{ Auth::user()->nama }}. Kebun Anda terpantau aman hari ini.
</div>

{{-- Stat Cards --}}
<div class="stat-grid">
    <div class="stat-card primary-card">
        <div class="stat-label">Total Sensor</div>
        <div class="stat-value">{{ $stats['total_sensor'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Sensor Online</div>
        <div class="stat-value" id="stat-online" style="color:#65A30D;">{{ $stats['sensor_online'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tanah Kering</div>
        <div class="stat-value" id="stat-kering" style="color:{{ $stats['tanah_kering'] > 0 ? '#D97706' : '#65A30D' }};">
            {{ $stats['tanah_kering'] }}
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pompa Aktif</div>
        <div class="stat-value" id="stat-pompa" style="color:#0284C7;">{{ $stats['penyiraman_aktif'] }}</div>
    </div>
</div>

{{-- Sensor List --}}
<div class="section-title">Kondisi Lokasi Kebun</div>

<div class="sensor-grid" id="sensor-grid">
    @forelse($sensorData as $row)
    <div class="sensor-card" id="dash-card-{{ $row->id_sensor }}" data-sensor="{{ $row->id_sensor }}">
        <div class="card-header">
            <div>
                <h3>{{ $row->nama_sensor }}</h3>
                <p>{{ $row->lokasi ?? 'Lokasi Umum' }}</p>
            </div>
            <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
                <span class="badge-status {{ $row->status ? 'badge-aktif' : 'badge-mati' }}"
                      id="badge-{{ $row->id_sensor }}">
                    {{ $row->status ? 'Aktif' : 'Mati' }}
                </span>
                <span class="pump-badge" id="pump-badge-{{ $row->id_sensor }}">Pompa OFF</span>
            </div>
        </div>

        <div class="sensor-readings">
            <div class="reading-box">
                <span class="reading-value" id="dash-kel-{{ $row->id_sensor }}">
                    {{ number_format($row->kelembapan, 0) }}%
                </span>
                <span class="reading-label">Kelembapan</span>
            </div>
            <div class="reading-box">
                <span class="reading-value" id="dash-ph-{{ $row->id_sensor }}">
                    {{ number_format($row->ph_tanah, 1) }}
                </span>
                <span class="reading-label">pH Tanah</span>
            </div>
        </div>

        <div class="sensor-footer">
            <span class="time" id="dash-time-{{ $row->id_sensor }}">
                {{ $row->created_at ? \Carbon\Carbon::parse($row->created_at)->diffForHumans() : 'Belum ada data' }}
            </span>
            <span class="condition" id="dash-cond-{{ $row->id_sensor }}">
                @if($row->kelembapan < 30)
                    <span class="condition-kering">[!] Perlu Air</span>
                @elseif($row->kelembapan > 70)
                    <span class="condition-basah">Terlalu Basah</span>
                @else
                    <span class="condition-aman">Aman</span>
                @endif
            </span>
        </div>
    </div>
    @empty
    <div class="empty-state">
        <h2>Belum ada data sensor.</h2>
        <p>Hubungi admin untuk mendaftarkan alat IoT Anda.</p>
    </div>
    @endforelse
</div>

{{-- IoT Signal Preview --}}
<div class="section-title">Sinyal Perangkat IoT</div>
<div class="iot-preview">
    <h3>Koneksi ke EMQX Broker</h3>
    <p>Data diterima via MQTT &bull; Diperbarui setiap 5 detik</p>
    <div class="signal-bars">
        @for($i = 0; $i < 20; $i++)
            <div class="signal-bar" style="height: {{ rand(20, 90) }}%; animation-delay: {{ $i * 0.1 }}s;"></div>
        @endfor
    </div>
</div>

@endsection

@push('scripts')
<script>
const DASH_USER_ID = {{ Auth::id() }};

async function pollDashboard() {
    try {
        const res  = await fetch(`/api/v1/sensors/live/all?user_id=${DASH_USER_ID}`, {
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (json.status !== 'success') return;

        let online = 0, kering = 0, pompaAktif = 0;

        json.data.forEach(sensor => {
            const sId = sensor.id_sensor;

            // Kelembapan
            const kelEl = document.getElementById(`dash-kel-${sId}`);
            if (kelEl) kelEl.textContent = sensor.kelembapan + '%';

            // pH
            const phEl = document.getElementById(`dash-ph-${sId}`);
            if (phEl) phEl.textContent = sensor.ph_tanah.toFixed(1);

            // Status badge
            const badge = document.getElementById(`badge-${sId}`);
            if (badge) {
                badge.textContent = sensor.online ? 'Aktif' : 'Mati';
                badge.className   = 'badge-status ' + (sensor.online ? 'badge-aktif' : 'badge-mati');
            }

            // Pump badge
            const pumpBadge = document.getElementById(`pump-badge-${sId}`);
            if (pumpBadge) {
                pumpBadge.textContent = sensor.pump_on ? '💧 Pompa ON' : 'Pompa OFF';
                pumpBadge.className   = 'pump-badge' + (sensor.pump_on ? ' on' : '');
            }

            // Kondisi
            const condEl = document.getElementById(`dash-cond-${sId}`);
            if (condEl) {
                if (sensor.kelembapan < 30) {
                    condEl.innerHTML = '<span class="condition-kering">[!] Perlu Air</span>';
                } else if (sensor.kelembapan > 70) {
                    condEl.innerHTML = '<span class="condition-basah">Terlalu Basah</span>';
                } else {
                    condEl.innerHTML = '<span class="condition-aman">Aman</span>';
                }
            }

            // Updated at
            const timeEl = document.getElementById(`dash-time-${sId}`);
            if (timeEl) timeEl.textContent = sensor.updated_at;

            // Hitung stats
            if (sensor.online) online++;
            if (sensor.kelembapan < 30) kering++;
            if (sensor.pump_on) pompaAktif++;
        });

        // Update stat cards
        const onlineEl = document.getElementById('stat-online');
        if (onlineEl) { onlineEl.textContent = online; }

        const keringEl = document.getElementById('stat-kering');
        if (keringEl) {
            keringEl.textContent = kering;
            keringEl.style.color = kering > 0 ? '#D97706' : '#65A30D';
        }

        const pompaEl = document.getElementById('stat-pompa');
        if (pompaEl) pompaEl.textContent = pompaAktif;

        // Timestamp
        const now = new Date();
        document.getElementById('dash-updated').textContent =
            'Live · ' + now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    } catch (err) {
        console.warn('[MAPIA Dashboard] Gagal:', err.message);
    }
}

pollDashboard();
setInterval(pollDashboard, 5000);
</script>
@endpush