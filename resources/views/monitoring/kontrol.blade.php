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
    .sensor-card:hover {
        border-color: var(--text);
    }
    .sensor-card-head {
        background: #0D0D0D;
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .sensor-name {
        color: #FFFFFF;
        font-size: 16px;
        font-weight: 700;
        font-family: 'Sora', sans-serif;
    }
    .sensor-loc {
        color: #888;
        font-size: 13px;
        margin-top: 4px;
    }
    .sensor-status-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--accent);
        flex-shrink: 0;
        box-shadow: 0 0 0 3px rgba(200, 241, 53, 0.25);
    }
    .sensor-status-dot.offline {
        background: #666;
        box-shadow: none;
    }

    .sensor-card-body {
        padding: 20px;
    }

    /* Pump status */
    .pump-status {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 20px;
    }
    .pump-on { background: rgba(200, 241, 53, 0.2); color: #0D0D0D; border: 1px solid var(--accent); }
    .pump-off { background: #F5F0E8; color: #666; border: 1px solid #E5E0D5; }
    .drip-wrap { display: flex; gap: 5px; align-items: flex-end; height: 20px; }
    .drip {
        width: 4px;
        border-radius: 99px;
        background: #0D0D0D;
        animation: drip-anim 1.2s ease-in-out infinite;
    }
    .drip:nth-child(1) { height: 16px; }
    .drip:nth-child(2) { animation-delay: 0.2s; height: 12px; }
    .drip:nth-child(3) { animation-delay: 0.4s; height: 8px; }
    @keyframes drip-anim {
        0%, 100% { opacity: 1; transform: translateY(0); }
        50% { opacity: 0.4; transform: translateY(4px); }
    }

    /* Data readings */
    .data-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .data-label {
        font-size: 14px;
        color: #666;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .data-value {
        font-size: 20px;
        font-weight: 700;
        color: var(--text);
        font-family: 'Sora', sans-serif;
    }
    .data-value.warn { color: #D97706; }
    .data-value.good { color: #65A30D; }

    .progress-bar {
        background: #E5E0D5;
        border-radius: 999px;
        height: 8px;
        overflow: hidden;
        margin: 4px 0 16px;
    }
    .progress-fill {
        height: 100%;
        border-radius: 999px;
        transition: width 0.5s ease;
    }
    .prog-dry { background: #D97706; }
    .prog-ok { background: #65A30D; }
    .prog-wet { background: #0284C7; }

    .divider {
        border: none;
        border-top: 1px solid #E5E0D5;
        margin: 16px 0;
    }

    /* Mode toggle */
    .mode-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    .mode-lbl {
        font-size: 14px;
        font-weight: 700;
        color: var(--text);
    }
    .switch-form {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .switch {
        position: relative;
        display: inline-block;
        width: 52px;
        height: 28px;
    }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider-sw {
        position: absolute;
        inset: 0;
        background: #E5E0D5;
        border-radius: 999px;
        cursor: pointer;
        transition: 0.3s;
    }
    .slider-sw:before {
        content: '';
        position: absolute;
        height: 20px;
        width: 20px;
        left: 4px;
        bottom: 4px;
        background: #fff;
        border-radius: 50%;
        transition: 0.3s;
        box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    }
    input:checked + .slider-sw { background: var(--accent); }
    input:checked + .slider-sw:before { transform: translateX(24px); background: #0D0D0D; }
    .switch-label {
        font-size: 13px;
        font-weight: 700;
        min-width: 60px;
    }

    /* Manual buttons */
    .manual-btns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .btn-on, .btn-off {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px;
        border: none;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        min-height: 48px;
        font-family: inherit;
        transition: all 0.2s;
        width: 100%;
    }
    .btn-on { background: #0D0D0D; color: #fff; }
    .btn-on:hover { background: #333; }
    .btn-on:disabled { background: #E5E0D5; color: #888; cursor: not-allowed; }
    .btn-off { background: #fff; color: #0D0D0D; border: 1px solid #E5E0D5; }
    .btn-off:hover { background: #F5F0E8; border-color: #0D0D0D; }
    .btn-off:disabled { background: #F5F0E8; color: #aaa; border-color: #E5E0D5; cursor: not-allowed; }

    /* Auto info */
    .auto-info {
        background: #F5F0E8;
        border: 1px solid #E5E0D5;
        border-radius: 12px;
        padding: 16px;
        font-size: 13px;
        color: #666;
    }
    .auto-info-title {
        font-weight: 700;
        margin-bottom: 8px;
        font-size: 14px;
        color: var(--text);
    }
    .auto-info-row {
        display: flex;
        justify-content: space-between;
        margin-top: 6px;
    }

    .no-data {
        text-align: center;
        padding: 80px 20px;
        color: #888;
        font-size: 15px;
    }

    @media (max-width: 600px) {
        .sensor-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="sensor-grid">
    @forelse($sensors as $sensor)
    @php
        $latest = $sensor->riwayat_sensors->last();
        $kel    = $latest->kelembapan ?? 0;
        $ph     = $latest->ph_tanah ?? 0;
        $progClass = $kel < 30 ? 'prog-dry' : ($kel > 70 ? 'prog-wet' : 'prog-ok');
        $param  = $sensor->parameterPenyiraman ?? null;
        $modeAuto = $sensor->kontrolSiram->mode_auto ?? true;
        $pumpActive = isset($penyiramanAktif[$sensor->id_sensor]) && $penyiramanAktif[$sensor->id_sensor];
        $online = $sensor->status;
    @endphp
    <div class="sensor-card">
        <div class="sensor-card-head">
            <div>
                <div class="sensor-name">{{ $sensor->nama_sensor }}</div>
                <div class="sensor-loc">{{ $sensor->lokasi ?? 'Lokasi tidak diset' }}</div>
            </div>
            <div class="sensor-status-dot {{ $online ? '' : 'offline' }}" title="{{ $online ? 'Online' : 'Offline' }}"></div>
        </div>
        <div class="sensor-card-body">
            {{-- Pump status --}}
            <!-- // TODO: IoT — pump status currently stored in DB field devices.pump_status
//             In Sprint 3, ESP32 will publish real pump state to:
//             MQTT topic: mapia/actuator/pump/status -->
            @if($pumpActive)
            <div class="pump-status pump-on">
                Pompa Sedang Menyiram
                <div class="drip-wrap">
                    <div class="drip"></div><div class="drip"></div><div class="drip"></div>
                </div>
            </div>
            @else
            <div class="pump-status pump-off">
                Pompa Tidak Aktif
            </div>
            @endif

            {{-- Data sensor --}}
            <div class="data-row">
                <span class="data-label">Kelembapan</span>
                <!-- // TODO: IoT — this moisture value is currently read from sensor_readings table
//             In Sprint 3, replace with live MQTT topic: mapia/sensor/moisture
//             ESP32 publishes every 30 seconds via WiFi to mosquitto broker -->
                <span class="data-value {{ $kel < 30 ? 'warn' : 'good' }}">{{ number_format($kel, 1) }}%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill {{ $progClass }}" style="width:{{ min(100, $kel) }}%"></div>
            </div>
            <div class="data-row">
                <span class="data-label">pH Tanah</span>
                <!-- // TODO: IoT — this pH value is currently read from sensor_readings table
//             In Sprint 3, replace with live MQTT topic: mapia/sensor/ph
//             Trigger notification if ph_value > 7 (abnormal for papaya) -->
                <span class="data-value">{{ number_format($ph, 1) }}</span>
            </div>

            <hr class="divider">

            {{-- Mode toggle --}}
            <!-- // TODO: IoT — this toggle currently updates DB field only
//             In Sprint 3, send MQTT command to ESP32:
//             topic: mapia/actuator/pump/command, payload: "ON" or "OFF"
// EXPAND: add scheduling/timer logic here — e.g. auto stop after N minutes
//         store schedule config in parameters table, column: auto_stop_minutes -->
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
                            onchange="this.form.submit()" aria-label="Toggle mode otomatis">
                        <span class="slider-sw"></span>
                    </label>
                    <span class="switch-label" style="color:{{ $modeAuto ? 'var(--text)' : '#aaa' }}">Otomatis</span>
                </div>
            </form>

            {{-- Auto: show threshold info --}}
            @if($modeAuto)
            <div class="auto-info">
                <div class="auto-info-title">Mode Otomatis Aktif</div>
                <div class="auto-info-row">
                    <span>Kelembapan Min:</span>
                    <strong>{{ number_format($param->min_kelembapan ?? 0, 1) }}%</strong>
                </div>
                <div class="auto-info-row">
                    <span>Kelembapan Maks:</span>
                    <strong>{{ number_format($param->max_kelembapan ?? 0, 1) }}%</strong>
                </div>
                <div class="auto-info-row">
                    <span>pH Min–Maks:</span>
                    <strong>{{ $param->min_ph ?? 0 }} – {{ $param->max_ph ?? 14 }}</strong>
                </div>
            </div>
            @else
            {{-- Manual: ON / OFF buttons --}}
            <div class="manual-btns">
                <form method="POST" action="{{ route('monitoring.nyalakan', $sensor->id_sensor) }}">
                    @csrf
                    <button type="submit" class="btn-on" {{ $pumpActive ? 'disabled' : '' }}>
                        Nyalakan Pompa
                    </button>
                </form>
                <form method="POST" action="{{ route('monitoring.matikan', $sensor->id_sensor) }}">
                    @csrf
                    <button type="submit" class="btn-off" {{ !$pumpActive ? 'disabled' : '' }}>
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
