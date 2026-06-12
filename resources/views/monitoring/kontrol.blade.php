@extends('layouts.app')

@section('title', 'Kontrol Penyiraman')
@section('page-title', 'Kontrol Penyiraman')
@section('page-subtitle', 'Kendalikan pompa air untuk setiap sensor secara langsung')

@push('styles')
<style>
    .sensor-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 16px;
    }
    .sensor-card {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E5E0D5;
        overflow: hidden;
        transition: all 0.25s ease;
    }
    .sensor-card:hover { border-color: var(--text); }
    .sensor-card-head {
        background: #0D0D0D;
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .sensor-name { color: #FFFFFF; font-size: 16px; font-weight: 700; font-family: 'Sora', sans-serif; }
    .sensor-loc  { color: #888; font-size: 13px; margin-top: 4px; }
    .sensor-status-dot {
        width: 12px; height: 12px; border-radius: 50%;
        background: var(--accent); flex-shrink: 0;
        box-shadow: 0 0 0 3px rgba(200, 241, 53, 0.25);
        transition: background 0.3s;
    }
    .sensor-status-dot.offline { background: #666; box-shadow: none; }

    .sensor-card-body { padding: 20px; }

    /* Live badge */
    .live-badge {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 11px; font-weight: 700; color: #65A30D;
        background: rgba(101,163,13,.1); padding: 3px 10px;
        border-radius: 999px; margin-bottom: 16px;
    }
    .live-dot {
        width: 6px; height: 6px; border-radius: 50%;
        background: #65A30D; animation: pulse-dot 1.5s ease-in-out infinite;
    }
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: 0.5; transform: scale(0.8); }
    }

    /* Pump status */
    .pump-status {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 16px; border-radius: 999px;
        font-size: 14px; font-weight: 600; margin-bottom: 20px;
        transition: all 0.4s ease;
    }
    .pump-on  { background: rgba(200, 241, 53, 0.2); color: #0D0D0D; border: 1px solid var(--accent); }
    .pump-off { background: #F5F0E8; color: #666; border: 1px solid #E5E0D5; }
    .drip-wrap { display: flex; gap: 5px; align-items: flex-end; height: 20px; }
    .drip { width: 4px; border-radius: 99px; background: #0D0D0D; animation: drip-anim 1.2s ease-in-out infinite; }
    .drip:nth-child(1) { height: 16px; }
    .drip:nth-child(2) { animation-delay: 0.2s; height: 12px; }
    .drip:nth-child(3) { animation-delay: 0.4s; height: 8px; }
    @keyframes drip-anim {
        0%, 100% { opacity: 1; transform: translateY(0); }
        50%       { opacity: 0.4; transform: translateY(4px); }
    }

    /* Data readings */
    .data-row {
        display: flex; justify-content: space-between;
        align-items: center; margin-bottom: 12px;
    }
    .data-label { font-size: 14px; color: #666; display: flex; align-items: center; gap: 8px; }
    .data-value {
        font-size: 20px; font-weight: 700; color: var(--text);
        font-family: 'Sora', sans-serif; transition: all 0.3s ease;
    }
    .data-value.warn { color: #D97706; }
    .data-value.good { color: #65A30D; }

    /* Flash animation saat data update */
    @keyframes flash-update {
        0%   { background: rgba(200, 241, 53, 0.3); }
        100% { background: transparent; }
    }
    .just-updated { animation: flash-update 1s ease-out forwards; border-radius: 6px; }

    .progress-bar { background: #E5E0D5; border-radius: 999px; height: 8px; overflow: hidden; margin: 4px 0 16px; }
    .progress-fill { height: 100%; border-radius: 999px; transition: width 0.8s ease; }
    .prog-dry { background: #D97706; }
    .prog-ok  { background: #65A30D; }
    .prog-wet { background: #0284C7; }

    .divider { border: none; border-top: 1px solid #E5E0D5; margin: 16px 0; }

    /* Mode toggle */
    .mode-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .mode-lbl { font-size: 14px; font-weight: 700; color: var(--text); }
    .switch-form { display: flex; align-items: center; gap: 12px; }
    .switch { position: relative; display: inline-block; width: 52px; height: 28px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider-sw {
        position: absolute; inset: 0; background: #E5E0D5;
        border-radius: 999px; cursor: pointer; transition: 0.3s;
    }
    .slider-sw:before {
        content: ''; position: absolute; height: 20px; width: 20px;
        left: 4px; bottom: 4px; background: #fff; border-radius: 50%;
        transition: 0.3s; box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    }
    input:checked + .slider-sw { background: var(--accent); }
    input:checked + .slider-sw:before { transform: translateX(24px); background: #0D0D0D; }
    .switch-label { font-size: 13px; font-weight: 700; min-width: 60px; }

    /* Manual buttons */
    .manual-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .btn-on, .btn-off {
        display: flex; align-items: center; justify-content: center;
        gap: 8px; padding: 12px; border: none; border-radius: 999px;
        font-size: 14px; font-weight: 700; cursor: pointer;
        min-height: 48px; font-family: inherit; transition: all 0.2s; width: 100%;
    }
    .btn-on { background: #0D0D0D; color: #fff; }
    .btn-on:hover { background: #333; }
    .btn-on:disabled { background: #E5E0D5; color: #888; cursor: not-allowed; }
    .btn-off { background: #fff; color: #0D0D0D; border: 1px solid #E5E0D5; }
    .btn-off:hover { background: #F5F0E8; border-color: #0D0D0D; }
    .btn-off:disabled { background: #F5F0E8; color: #aaa; border-color: #E5E0D5; cursor: not-allowed; }

    /* Kondisi badge */
    .kondisi-badge {
        font-size: 11px; font-weight: 700; padding: 3px 10px;
        border-radius: 999px; margin-left: 8px; text-transform: uppercase;
    }
    .kondisi-NORMAL     { background: rgba(101,163,13,.1); color: #65A30D; }
    .kondisi-KERING     { background: rgba(217,119,6,.1); color: #D97706; }
    .kondisi-BASAH      { background: rgba(2,132,199,.1); color: #0284C7; }
    .kondisi-PH_ABNORMAL{ background: rgba(239,68,68,.1); color: #DC2626; }
    .kondisi-UNKNOWN    { background: #E5E0D5; color: #888; }

    /* Auto info */
    .auto-info {
        background: #F5F0E8; border: 1px solid #E5E0D5;
        border-radius: 12px; padding: 16px; font-size: 13px; color: #666;
    }
    .auto-info-title { font-weight: 700; margin-bottom: 8px; font-size: 14px; color: var(--text); }
    .auto-info-row { display: flex; justify-content: space-between; margin-top: 6px; }

    /* Timestamp */
    .last-update { font-size: 11px; color: #aaa; margin-top: 12px; text-align: right; }

    .no-data { text-align: center; padding: 80px 20px; color: #888; font-size: 15px; }

    @media (max-width: 600px) {
        .sensor-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="sensor-grid">
    @forelse($sensors as $sensor)
    @php
        $latest   = $sensor->historyKelembapans->first();
        $kel      = $latest?->kelembapan ?? 0;
        $kondisi  = $latest?->kondisi ?? 'UNKNOWN';
        $param    = $sensor->parameterPenyiraman ?? null;
        $modeAuto = $sensor->kontrolSiram?->mode_auto ?? true;
        $pumpOn   = $sensor->kontrolSiram?->status_pompa ?? false;
        $online   = $sensor->status;
        $progClass = $kel < 30 ? 'prog-dry' : ($kel > 70 ? 'prog-wet' : 'prog-ok');
        $kelClass  = $kel < ($param?->min_kelembapan ?? 40) ? 'warn' : 'good';
    @endphp

    {{-- data-sensor-id dipakai JS untuk update realtime --}}
    <div class="sensor-card" data-sensor-id="{{ $sensor->id_sensor }}">
        <div class="sensor-card-head">
            <div>
                <div class="sensor-name">{{ $sensor->nama_sensor }}</div>
                <div class="sensor-loc">{{ $sensor->lokasi ?? 'Lokasi tidak diset' }}</div>
            </div>
            <div class="sensor-status-dot {{ $online ? '' : 'offline' }}"
                 data-status-dot="{{ $sensor->id_sensor }}"
                 title="{{ $online ? 'Online' : 'Offline' }}"></div>
        </div>

        <div class="sensor-card-body">
            {{-- Live badge --}}
            <div class="live-badge">
                <span class="live-dot"></span> LIVE
            </div>

            {{-- Pump status --}}
            <div class="pump-status {{ $pumpOn ? 'pump-on' : 'pump-off' }}"
                 data-pump-status="{{ $sensor->id_sensor }}">
                @if($pumpOn)
                    Pompa Sedang Menyiram
                    <div class="drip-wrap">
                        <div class="drip"></div><div class="drip"></div><div class="drip"></div>
                    </div>
                @else
                    Pompa Tidak Aktif
                @endif
            </div>

            {{-- Data kelembapan --}}
            <div class="data-row" data-reading-row="{{ $sensor->id_sensor }}">
                <span class="data-label">
                    Kelembapan
                    <span class="kondisi-badge kondisi-{{ $kondisi }}"
                          data-kondisi-badge="{{ $sensor->id_sensor }}">{{ $kondisi }}</span>
                </span>
                <span class="data-value {{ $kelClass }}"
                      data-kel-value="{{ $sensor->id_sensor }}">
                    {{ number_format($kel, 1) }}%
                </span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill {{ $progClass }}"
                     data-kel-bar="{{ $sensor->id_sensor }}"
                     style="width:{{ min(100, $kel) }}%"></div>
            </div>

            {{-- Timestamp terakhir update --}}
            <div class="last-update" data-last-update="{{ $sensor->id_sensor }}">
                {{ $latest?->created_at ? '↻ ' . $latest->created_at->diffForHumans() : 'Menunggu data...' }}
            </div>

            <hr class="divider">

            {{-- Mode toggle --}}
            <div class="mode-row">
                <span class="mode-lbl">Mode Penyiraman</span>
            </div>
            <form method="POST" action="{{ route('monitoring.toggle-mode', $sensor->id_sensor) }}" style="margin-bottom: 20px;">
                @csrf
                @method('PATCH')
                <div class="switch-form">
                    <span class="switch-label" style="color:{{ $modeAuto ? '#aaa' : 'var(--text)' }}">Manual</span>
                    <label class="switch">
                        <input type="checkbox" name="mode_auto" value="1"
                               {{ $modeAuto ? 'checked' : '' }}
                               onchange="this.form.submit()"
                               aria-label="Toggle mode otomatis">
                        <span class="slider-sw"></span>
                    </label>
                    <span class="switch-label" style="color:{{ $modeAuto ? 'var(--text)' : '#aaa' }}">Otomatis</span>
                </div>
            </form>

            @if($modeAuto)
            {{-- Mode otomatis: tampilkan threshold --}}
            <div class="auto-info">
                <div class="auto-info-title">Mode Otomatis Aktif</div>
                <div class="auto-info-row">
                    <span>Kelembapan Min:</span>
                    <strong>{{ number_format($param?->min_kelembapan ?? 0, 1) }}%</strong>
                </div>
                <div class="auto-info-row">
                    <span>Kelembapan Maks:</span>
                    <strong>{{ number_format($param?->max_kelembapan ?? 0, 1) }}%</strong>
                </div>
                <div class="auto-info-row">
                    <span>pH Min–Maks:</span>
                    <strong>{{ $param?->min_ph ?? 0 }} – {{ $param?->max_ph ?? 14 }}</strong>
                </div>
            </div>
            @else
            {{-- Mode manual: tombol ON/OFF --}}
            <div class="manual-btns" data-manual-btns="{{ $sensor->id_sensor }}">
                <form method="POST" action="{{ route('monitoring.nyalakan', $sensor->id_sensor) }}">
                    @csrf
                    <button type="submit" class="btn-on" {{ $pumpOn ? 'disabled' : '' }}
                            data-btn-on="{{ $sensor->id_sensor }}">
                        Nyalakan Pompa
                    </button>
                </form>
                <form method="POST" action="{{ route('monitoring.matikan', $sensor->id_sensor) }}">
                    @csrf
                    <button type="submit" class="btn-off" {{ !$pumpOn ? 'disabled' : '' }}
                            data-btn-off="{{ $sensor->id_sensor }}">
                        Matikan Pompa
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>
    @empty
    <div class="no-data" style="grid-column: 1/-1;">
        Tidak ada sensor yang terdaftar.<br>
        <small>Hubungi administrator untuk menambah sensor.</small>
    </div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
// ════════════════════════════════════════════════════════════
// Realtime update via Laravel Echo (WebSocket Reverb)
// Event: SensorDataUpdated di channel sensor.{id}
// ════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {
    // Pastikan Laravel Echo sudah di-load
    if (typeof window.Echo === 'undefined') {
        console.warn('[MAPIA] Laravel Echo belum dimuat — pastikan app.js di-include.');
        return;
    }

    // Subscribe ke channel untuk setiap sensor yang ada di halaman
    document.querySelectorAll('[data-sensor-id]').forEach(function (card) {
        const sensorId = card.dataset.sensorId;

        window.Echo.channel('sensor.' + sensorId)
            .listen('.sensor-updated', function (data) {
                console.log('[MAPIA] Data masuk untuk sensor', sensorId, data);
                updateSensorCard(sensorId, data);
            });

        console.log('[MAPIA] Subscribe ke channel: sensor.' + sensorId);
    });

    // Juga subscribe ke channel sensors (semua sensor sekaligus)
    window.Echo.channel('sensors')
        .listen('.sensor-updated', function (data) {
            updateSensorCard(data.sensorId, data);
        });
});

/**
 * Update semua elemen UI untuk sensor tertentu
 * dipanggil saat WebSocket event masuk
 */
function updateSensorCard(sensorId, data) {
    const kelembapan  = parseFloat(data.kelembapan) || 0;
    const kondisi     = data.kondisi   || 'UNKNOWN';
    const pump        = data.pump      || 'OFF';
    const mode        = data.mode      || 'otomatis';
    const pumpIsOn    = pump === 'ON';

    // ── 1. Update nilai kelembapan ──
    const kelEl = document.querySelector('[data-kel-value="' + sensorId + '"]');
    if (kelEl) {
        kelEl.textContent = kelembapan.toFixed(1) + '%';
        // Update warna berdasarkan nilai
        kelEl.className = 'data-value ' + (kelembapan < 40 ? 'warn' : 'good');
        // Flash effect
        kelEl.closest('.data-row')?.classList.add('just-updated');
        setTimeout(() => kelEl.closest('.data-row')?.classList.remove('just-updated'), 1000);
    }

    // ── 2. Update progress bar kelembapan ──
    const barEl = document.querySelector('[data-kel-bar="' + sensorId + '"]');
    if (barEl) {
        barEl.style.width = Math.min(100, kelembapan) + '%';
        barEl.className = 'progress-fill ' + (
            kelembapan < 30 ? 'prog-dry' : (kelembapan > 70 ? 'prog-wet' : 'prog-ok')
        );
    }

    // ── 3. Update kondisi badge ──
    const kondisiEl = document.querySelector('[data-kondisi-badge="' + sensorId + '"]');
    if (kondisiEl) {
        kondisiEl.textContent = kondisi;
        kondisiEl.className = 'kondisi-badge kondisi-' + kondisi;
    }

    // ── 4. Update pump status display ──
    const pumpEl = document.querySelector('[data-pump-status="' + sensorId + '"]');
    if (pumpEl) {
        if (pumpIsOn) {
            pumpEl.className = 'pump-status pump-on';
            pumpEl.innerHTML = 'Pompa Sedang Menyiram <div class="drip-wrap"><div class="drip"></div><div class="drip"></div><div class="drip"></div></div>';
        } else {
            pumpEl.className = 'pump-status pump-off';
            pumpEl.innerHTML = 'Pompa Tidak Aktif';
        }
    }

    // ── 5. Update status dot (online/offline) ──
    const dotEl = document.querySelector('[data-status-dot="' + sensorId + '"]');
    if (dotEl) {
        dotEl.classList.remove('offline');
    }

    // ── 6. Update tombol ON/OFF di mode manual ──
    const btnOn  = document.querySelector('[data-btn-on="' + sensorId + '"]');
    const btnOff = document.querySelector('[data-btn-off="' + sensorId + '"]');
    if (btnOn)  btnOn.disabled  = pumpIsOn;
    if (btnOff) btnOff.disabled = !pumpIsOn;

    // ── 7. Update timestamp ──
    const timeEl = document.querySelector('[data-last-update="' + sensorId + '"]');
    if (timeEl) {
        const now = new Date();
        timeEl.textContent = '↻ baru saja diperbarui — ' +
            now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
}
</script>
@endpush