@extends('layouts.app')

@section('title', 'Kontrol Penyiraman')
@section('page-title', 'Kontrol Penyiraman')
@section('page-subtitle', 'Kendalikan pompa air — data diperbarui langsung dari perangkat IoT')

@push('styles')
<style>
    /* ── Live indicator ── */
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
        flex-shrink: 0;
    }
    @keyframes live-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: 0.4; transform: scale(0.8); }
    }
    #last-updated { color: #888; font-weight: 400; }

    /* ── Grid ── */
    .sensor-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 20px;
    }

    /* ── Card ── */
    .sensor-card {
        background: #FFFFFF;
        border-radius: 20px;
        border: 1px solid #E5E0D5;
        overflow: hidden;
        transition: border-color 0.25s ease;
    }
    .sensor-card:hover { border-color: #0D0D0D; }

    .sensor-card-head {
        background: #0D0D0D;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .sensor-name {
        color: #fff;
        font-size: 16px;
        font-weight: 700;
        font-family: 'Sora', sans-serif;
    }
    .sensor-loc { color: #888; font-size: 13px; margin-top: 3px; }
    .sensor-status-dot {
        width: 12px; height: 12px;
        border-radius: 50%;
        background: var(--accent);
        box-shadow: 0 0 0 3px rgba(200,241,53,.25);
        flex-shrink: 0;
        transition: background 0.3s;
    }
    .sensor-status-dot.offline { background: #555; box-shadow: none; }

    .sensor-card-body { padding: 20px 24px; }

    /* ── Pump switch hero ── */
    .pump-hero {
        background: #F5F0E8;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        transition: background 0.4s ease;
    }
    .pump-hero.active {
        background: #0D0D0D;
    }
    .pump-hero-left { flex: 1; min-width: 0; }
    .pump-hero-label {
        font-size: 13px;
        font-weight: 700;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
        transition: color 0.3s;
    }
    .pump-hero.active .pump-hero-label { color: #666; }
    .pump-hero-state {
        font-size: 22px;
        font-weight: 800;
        color: #0D0D0D;
        font-family: 'Sora', sans-serif;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: color 0.3s;
    }
    .pump-hero.active .pump-hero-state { color: var(--accent); }

    /* Drip animation */
    .drip-wrap { display: flex; gap: 4px; align-items: flex-end; height: 18px; }
    .drip {
        width: 4px; border-radius: 99px;
        background: var(--accent);
        animation: drip-anim 1s ease-in-out infinite;
    }
    .drip:nth-child(1) { height: 14px; }
    .drip:nth-child(2) { height: 10px; animation-delay: .2s; }
    .drip:nth-child(3) { height: 6px;  animation-delay: .4s; }
    @keyframes drip-anim {
        0%,100% { opacity:1; transform:translateY(0); }
        50%      { opacity:.4; transform:translateY(3px); }
    }

    /* ── Big pump toggle switch ── */
    .pump-toggle-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }
    .pump-switch {
        position: relative;
        width: 68px; height: 36px;
        cursor: pointer;
    }
    .pump-switch input { opacity: 0; width: 0; height: 0; }
    .pump-track {
        position: absolute;
        inset: 0;
        background: #D5D0C5;
        border-radius: 999px;
        transition: background 0.3s ease;
    }
    .pump-track::before {
        content: '';
        position: absolute;
        height: 28px; width: 28px;
        left: 4px; bottom: 4px;
        background: #fff;
        border-radius: 50%;
        transition: transform 0.3s cubic-bezier(.4,0,.2,1);
        box-shadow: 0 2px 6px rgba(0,0,0,.2);
    }
    .pump-switch input:checked + .pump-track { background: var(--accent); }
    .pump-switch input:checked + .pump-track::before {
        transform: translateX(32px);
        background: #0D0D0D;
    }
    .pump-switch input:disabled + .pump-track { opacity: 0.4; cursor: not-allowed; }
    .pump-toggle-label {
        font-size: 11px;
        font-weight: 700;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .pump-hero.active .pump-toggle-label { color: #555; }

    /* ── Readings ── */
    .readings-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 20px;
    }
    .reading-box {
        background: #F5F0E8;
        border-radius: 12px;
        padding: 14px 12px;
        text-align: center;
        transition: background 0.3s;
    }
    .reading-val {
        font-size: 28px;
        font-weight: 800;
        font-family: 'Sora', sans-serif;
        display: block;
        margin-bottom: 3px;
        transition: color 0.3s;
    }
    .reading-lbl {
        font-size: 12px;
        color: #888;
        font-weight: 600;
    }
    .val-dry  { color: #D97706; }
    .val-ok   { color: #65A30D; }
    .val-wet  { color: #0284C7; }
    .val-norm { color: #0D0D0D; }

    /* progress bar */
    .progress-wrap { margin-bottom: 20px; }
    .progress-meta {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: #888;
        margin-bottom: 6px;
        font-weight: 600;
    }
    .progress-bar {
        background: #E5E0D5;
        border-radius: 999px;
        height: 8px;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        border-radius: 999px;
        transition: width 0.6s ease;
    }
    .prog-dry { background: #D97706; }
    .prog-ok  { background: #65A30D; }
    .prog-wet { background: #0284C7; }

    .divider { border: none; border-top: 1px solid #E5E0D5; margin: 16px 0; }

    /* ── Mode section ── */
    .mode-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }
    .mode-lbl { font-size: 14px; font-weight: 700; color: var(--text); }
    .mode-switch-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .mode-switch {
        position: relative;
        display: inline-block;
        width: 52px; height: 28px;
        cursor: pointer;
    }
    .mode-switch input { opacity: 0; width: 0; height: 0; }
    .mode-track {
        position: absolute;
        inset: 0;
        background: #E5E0D5;
        border-radius: 999px;
        transition: background 0.3s;
    }
    .mode-track::before {
        content: '';
        position: absolute;
        height: 20px; width: 20px;
        left: 4px; bottom: 4px;
        background: #fff;
        border-radius: 50%;
        transition: transform 0.3s cubic-bezier(.4,0,.2,1);
        box-shadow: 0 1px 4px rgba(0,0,0,.1);
    }
    .mode-switch input:checked + .mode-track { background: var(--accent); }
    .mode-switch input:checked + .mode-track::before {
        transform: translateX(24px);
        background: #0D0D0D;
    }
    .mode-text {
        font-size: 13px; font-weight: 700;
        min-width: 60px;
        transition: color 0.2s;
    }

    /* ── Auto info ── */
    .auto-info {
        background: #F5F0E8;
        border-radius: 12px;
        padding: 16px;
        font-size: 13px;
    }
    .auto-info-title {
        font-weight: 700;
        font-size: 14px;
        color: var(--text);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .auto-info-row {
        display: flex;
        justify-content: space-between;
        color: #666;
        margin-top: 6px;
    }
    .auto-info-row strong { color: var(--text); font-weight: 700; }

    /* ── Toast ── */
    #toast {
        position: fixed;
        bottom: 32px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        background: #0D0D0D;
        color: #fff;
        padding: 12px 28px;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 600;
        opacity: 0;
        pointer-events: none;
        transition: all 0.3s ease;
        z-index: 9999;
        white-space: nowrap;
    }
    #toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    #toast.success { background: #0D0D0D; }
    #toast.error   { background: #991B1B; }

    /* ── Updated at ── */
    .sensor-updated {
        font-size: 12px;
        color: #aaa;
        margin-top: 12px;
        text-align: right;
    }

    /* ── Loading skeleton ── */
    .skeleton {
        background: linear-gradient(90deg, #F0EBE0 25%, #E5E0D5 50%, #F0EBE0 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        border-radius: 8px;
    }
    @keyframes shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .no-data {
        text-align: center;
        padding: 80px 20px;
        color: #888;
    }

    @media (max-width: 600px) {
        .sensor-grid { grid-template-columns: 1fr; }
        .readings-row { grid-template-columns: 1fr 1fr; }
    }
</style>
@endpush

@section('content')

{{-- Live indicator --}}
<div class="live-bar">
    <div class="live-dot"></div>
    <span>Data Live dari IoT</span>
    <span id="last-updated">— memuat...</span>
</div>

{{-- Sensor grid --}}
<div class="sensor-grid" id="sensor-grid">
    @forelse($sensors as $sensor)
    @php
        $latest    = $sensor->riwayat_sensors->last();
        $kel       = $latest->kelembapan ?? 0;
        $ph        = $latest->ph_tanah ?? 0;
        $progClass = $kel < 30 ? 'prog-dry' : ($kel > 70 ? 'prog-wet' : 'prog-ok');
        $valClass  = $kel < 30 ? 'val-dry'  : ($kel > 70 ? 'val-wet'  : 'val-ok');
        $param     = $sensor->parameterPenyiraman ?? null;
        $modeAuto  = $sensor->kontrolSiram->mode_auto ?? true;
        $pumpOn    = $sensor->kontrolSiram->status_pompa ?? false;
        $online    = $sensor->status;
        $kondisi   = 'UNKNOWN';
    @endphp
    <div class="sensor-card" id="card-{{ $sensor->id_sensor }}" data-sensor="{{ $sensor->id_sensor }}">
        <div class="sensor-card-head">
            <div>
                <div class="sensor-name">{{ $sensor->nama_sensor }}</div>
                <div class="sensor-loc">{{ $sensor->lokasi ?? 'Lokasi tidak diset' }}</div>
            </div>
            <div class="sensor-status-dot {{ $online ? '' : 'offline' }}"
                 id="dot-{{ $sensor->id_sensor }}"
                 title="{{ $online ? 'Online' : 'Offline' }}"></div>
        </div>
        <div class="sensor-card-body">

            {{-- ── POMPA SWITCH HERO ── --}}
            <div class="pump-hero {{ $pumpOn ? 'active' : '' }}" id="pump-hero-{{ $sensor->id_sensor }}">
                <div class="pump-hero-left">
                    <div class="pump-hero-label">Pompa Air</div>
                    <div class="pump-hero-state" id="pump-state-{{ $sensor->id_sensor }}">
                        @if($pumpOn)
                            Menyiram
                            <div class="drip-wrap">
                                <div class="drip"></div><div class="drip"></div><div class="drip"></div>
                            </div>
                        @else
                            Tidak Aktif
                        @endif
                    </div>
                </div>
                <div class="pump-toggle-wrap">
                    <label class="pump-switch" title="{{ $modeAuto ? 'Nonaktifkan mode otomatis dulu untuk kontrol manual' : 'Aktifkan/matikan pompa' }}">
                        <input type="checkbox"
                               id="pump-switch-{{ $sensor->id_sensor }}"
                               {{ $pumpOn ? 'checked' : '' }}
                               {{ $modeAuto ? 'disabled' : '' }}
                               data-sensor="{{ $sensor->id_sensor }}"
                               onchange="togglePump(this)">
                        <span class="pump-track"></span>
                    </label>
                    <div class="pump-toggle-label" id="pump-switch-lbl-{{ $sensor->id_sensor }}">
                        {{ $modeAuto ? 'Auto' : ($pumpOn ? 'ON' : 'OFF') }}
                    </div>
                </div>
            </div>

            {{-- ── READINGS ── --}}
            <div class="readings-row">
                <div class="reading-box">
                    <span class="reading-val {{ $valClass }}" id="kel-{{ $sensor->id_sensor }}">
                        {{ number_format($kel, 1) }}%
                    </span>
                    <span class="reading-lbl">Kelembapan</span>
                </div>
                <div class="reading-box">
                    <span class="reading-val val-norm" id="ph-{{ $sensor->id_sensor }}">
                        {{ number_format($ph, 2) }}
                    </span>
                    <span class="reading-lbl">pH Tanah</span>
                </div>
            </div>

            {{-- Progress kelembapan --}}
            <div class="progress-wrap">
                <div class="progress-meta">
                    <span id="kondisi-{{ $sensor->id_sensor }}">{{ $kondisi }}</span>
                    <span id="kel-pct-{{ $sensor->id_sensor }}">{{ number_format($kel, 1) }}%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill {{ $progClass }}"
                         id="prog-{{ $sensor->id_sensor }}"
                         style="width: {{ min(100, $kel) }}%"></div>
                </div>
            </div>

            <hr class="divider">

            {{-- ── MODE TOGGLE ── --}}
            <div class="mode-row">
                <span class="mode-lbl">Mode Penyiraman</span>
            </div>
            <div class="mode-switch-wrap" style="margin-bottom: 16px;">
                <span class="mode-text" id="mode-manual-lbl-{{ $sensor->id_sensor }}"
                      style="color: {{ $modeAuto ? '#aaa' : 'var(--text)' }}">Manual</span>
                <label class="mode-switch">
                    <input type="checkbox"
                           id="mode-switch-{{ $sensor->id_sensor }}"
                           {{ $modeAuto ? 'checked' : '' }}
                           data-sensor="{{ $sensor->id_sensor }}"
                           onchange="toggleMode(this)">
                    <span class="mode-track"></span>
                </label>
                <span class="mode-text" id="mode-auto-lbl-{{ $sensor->id_sensor }}"
                      style="color: {{ $modeAuto ? 'var(--text)' : '#aaa' }}">Otomatis</span>
            </div>

            {{-- Auto info / Manual locked msg --}}
            <div id="mode-info-{{ $sensor->id_sensor }}">
                @if($modeAuto)
                <div class="auto-info">
                    <div class="auto-info-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        Mode Otomatis Aktif
                    </div>
                    <div class="auto-info-row">
                        <span>Siram saat kelembapan di bawah:</span>
                        <strong id="param-min-{{ $sensor->id_sensor }}">{{ number_format($param->min_kelembapan ?? 40, 0) }}%</strong>
                    </div>
                    <div class="auto-info-row">
                        <span>Berhenti di atas:</span>
                        <strong id="param-max-{{ $sensor->id_sensor }}">{{ number_format($param->max_kelembapan ?? 70, 0) }}%</strong>
                    </div>
                    <div class="auto-info-row">
                        <span>pH aman:</span>
                        <strong>{{ $param->min_ph ?? 6 }} – {{ $param->max_ph ?? 7 }}</strong>
                    </div>
                </div>
                @else
                <div style="background:#F5F0E8; border-radius:12px; padding:14px 16px; font-size:13px; color:#888;">
                    Mode manual aktif. Gunakan saklar pompa di atas untuk mengontrol penyiraman.
                </div>
                @endif
            </div>

            <div class="sensor-updated" id="updated-{{ $sensor->id_sensor }}">
                Terakhir diperbarui: {{ $sensor->riwayat_sensors->last() ? \Carbon\Carbon::parse($sensor->riwayat_sensors->last()->created_at)->diffForHumans() : 'belum ada data' }}
            </div>
        </div>
    </div>
    @empty
    <div class="no-data" style="grid-column:1/-1;">
        Tidak ada sensor yang terdaftar.
    </div>
    @endforelse
</div>

{{-- Toast notifikasi --}}
<div id="toast"></div>

@endsection

@push('scripts')
<script>
// ════════════════════════════════════════════════════════════
// CONFIG
// ════════════════════════════════════════════════════════════
const POLL_INTERVAL = 5000; // 5 detik
const USER_ID       = {{ Auth::id() }};
const CSRF_TOKEN    = document.querySelector('meta[name="csrf-token"]').content;

// ════════════════════════════════════════════════════════════
// TOAST
// ════════════════════════════════════════════════════════════
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show ' + type;
    setTimeout(() => { t.className = ''; }, 3000);
}

// ════════════════════════════════════════════════════════════
// TOGGLE POMPA — kirim ke API → MQTT → ESP32
// ════════════════════════════════════════════════════════════
async function togglePump(checkbox) {
    const sensorId = checkbox.dataset.sensor;
    const action   = checkbox.checked ? 'ON' : 'OFF';

    // Disable sementara agar tidak double-click
    checkbox.disabled = true;

    try {
        const res = await fetch(`/api/v1/sensors/${sensorId}/pump`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ action }),
        });

        const data = await res.json();

        if (data.status === 'success') {
            updatePumpUI(sensorId, action === 'ON');
            showToast(`Pompa ${action === 'ON' ? 'dinyalakan' : 'dimatikan'} ✓`);
        } else {
            // Kembalikan toggle kalau gagal
            checkbox.checked = !checkbox.checked;
            showToast(data.message || 'Gagal mengontrol pompa', 'error');
        }
    } catch (err) {
        checkbox.checked = !checkbox.checked;
        showToast('Koneksi bermasalah', 'error');
    } finally {
        checkbox.disabled = false;
    }
}

// ════════════════════════════════════════════════════════════
// UPDATE PUMP UI
// ════════════════════════════════════════════════════════════
function updatePumpUI(sensorId, pumpOn) {
    const hero  = document.getElementById(`pump-hero-${sensorId}`);
    const state = document.getElementById(`pump-state-${sensorId}`);
    const sw    = document.getElementById(`pump-switch-${sensorId}`);
    const lbl   = document.getElementById(`pump-switch-lbl-${sensorId}`);

    if (!hero) return;

    if (pumpOn) {
        hero.classList.add('active');
        state.innerHTML = `Menyiram <div class="drip-wrap"><div class="drip"></div><div class="drip"></div><div class="drip"></div></div>`;
        if (lbl) lbl.textContent = 'ON';
    } else {
        hero.classList.remove('active');
        state.innerHTML = 'Tidak Aktif';
        if (lbl) lbl.textContent = 'OFF';
    }

    if (sw) sw.checked = pumpOn;
}

// ════════════════════════════════════════════════════════════
// TOGGLE MODE — otomatis/manual
// ════════════════════════════════════════════════════════════
async function toggleMode(checkbox) {
    const sensorId = checkbox.dataset.sensor;
    const mode     = checkbox.checked ? 'otomatis' : 'manual';

    checkbox.disabled = true;

    try {
        const res = await fetch(`/api/v1/sensors/${sensorId}/mode`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ mode }),
        });

        const data = await res.json();

        if (data.status === 'success') {
            updateModeUI(sensorId, mode === 'otomatis');
            showToast(`Mode diubah ke ${mode === 'otomatis' ? 'Otomatis 🤖' : 'Manual 👋'}`);
        } else {
            checkbox.checked = !checkbox.checked;
            showToast(data.message || 'Gagal mengubah mode', 'error');
        }
    } catch (err) {
        checkbox.checked = !checkbox.checked;
        showToast('Koneksi bermasalah', 'error');
    } finally {
        checkbox.disabled = false;
    }
}

// ════════════════════════════════════════════════════════════
// UPDATE MODE UI
// ════════════════════════════════════════════════════════════
function updateModeUI(sensorId, isAuto) {
    const pumpSwitch = document.getElementById(`pump-switch-${sensorId}`);
    const pumpLbl    = document.getElementById(`pump-switch-lbl-${sensorId}`);
    const manualLbl  = document.getElementById(`mode-manual-lbl-${sensorId}`);
    const autoLbl    = document.getElementById(`mode-auto-lbl-${sensorId}`);
    const modeInfo   = document.getElementById(`mode-info-${sensorId}`);

    if (pumpSwitch) {
        pumpSwitch.disabled = isAuto;
        if (isAuto) {
            // Matikan pompa saat ganti ke auto
            pumpSwitch.checked = false;
            updatePumpUI(sensorId, false);
        }
    }
    if (pumpLbl) pumpLbl.textContent = isAuto ? 'Auto' : (pumpSwitch?.checked ? 'ON' : 'OFF');
    if (manualLbl) manualLbl.style.color = isAuto ? '#aaa' : 'var(--text)';
    if (autoLbl)   autoLbl.style.color   = isAuto ? 'var(--text)' : '#aaa';

    // Update info box
    if (modeInfo) {
        if (isAuto) {
            // Ambil parameter dari data yang sudah ada di DOM
            const minEl = document.getElementById(`param-min-${sensorId}`);
            const maxEl = document.getElementById(`param-max-${sensorId}`);
            const minVal = minEl ? minEl.textContent : '40%';
            const maxVal = maxEl ? maxEl.textContent : '70%';

            modeInfo.innerHTML = `
            <div class="auto-info">
                <div class="auto-info-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    Mode Otomatis Aktif
                </div>
                <div class="auto-info-row">
                    <span>Siram saat kelembapan di bawah:</span>
                    <strong id="param-min-${sensorId}">${minVal}</strong>
                </div>
                <div class="auto-info-row">
                    <span>Berhenti di atas:</span>
                    <strong id="param-max-${sensorId}">${maxVal}</strong>
                </div>
            </div>`;
        } else {
            modeInfo.innerHTML = `
            <div style="background:#F5F0E8; border-radius:12px; padding:14px 16px; font-size:13px; color:#888;">
                Mode manual aktif. Gunakan saklar pompa di atas untuk mengontrol penyiraman.
            </div>`;
        }
    }
}

// ════════════════════════════════════════════════════════════
// POLLING — ambil data live dari IoT setiap 5 detik
// ════════════════════════════════════════════════════════════
async function pollSensorData() {
    try {
        const res  = await fetch(`/api/v1/sensors/live/all?user_id=${USER_ID}`, {
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();

        if (json.status !== 'success') return;

        json.data.forEach(sensor => {
            const sId = sensor.id_sensor;

            // Kelembapan
            const kelEl = document.getElementById(`kel-${sId}`);
            if (kelEl) {
                kelEl.textContent = sensor.kelembapan + '%';
                kelEl.className = 'reading-val ' + getKelClass(sensor.kelembapan);
            }

            // pH
            const phEl = document.getElementById(`ph-${sId}`);
            if (phEl) phEl.textContent = sensor.ph_tanah.toFixed(2);

            // Progress bar
            const prog = document.getElementById(`prog-${sId}`);
            if (prog) {
                prog.style.width = Math.min(100, sensor.kelembapan) + '%';
                prog.className = 'progress-fill ' + getProgClass(sensor.kelembapan);
            }

            // Kondisi label
            const kondisiEl = document.getElementById(`kondisi-${sId}`);
            if (kondisiEl) kondisiEl.textContent = sensor.kondisi;

            const kelPct = document.getElementById(`kel-pct-${sId}`);
            if (kelPct) kelPct.textContent = sensor.kelembapan + '%';

            // Online dot
            const dot = document.getElementById(`dot-${sId}`);
            if (dot) {
                dot.className = 'sensor-status-dot ' + (sensor.online ? '' : 'offline');
            }

            // Pump state (hanya update jika mode auto — manual dihandle user)
            if (sensor.mode_auto) {
                updatePumpUI(sId, sensor.pump_on);
            }

            // Updated at
            const updEl = document.getElementById(`updated-${sId}`);
            if (updEl) updEl.textContent = 'Terakhir diperbarui: ' + sensor.updated_at;
        });

        // Update timestamp global
        const now = new Date();
        document.getElementById('last-updated').textContent =
            '— diperbarui ' + now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    } catch (err) {
        // Silent fail — jangan ganggu UI
        console.warn('[MAPIA Poll] Gagal:', err.message);
    }
}

function getKelClass(k) {
    if (k < 30) return 'val-dry';
    if (k > 70) return 'val-wet';
    return 'val-ok';
}
function getProgClass(k) {
    if (k < 30) return 'prog-dry';
    if (k > 70) return 'prog-wet';
    return 'prog-ok';
}

// ════════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════════
pollSensorData(); // langsung poll saat halaman dimuat
setInterval(pollSensorData, POLL_INTERVAL);
</script>
@endpush