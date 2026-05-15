@extends('layouts.app')

@section('title', 'Pengaturan Parameter')
@section('page-title', 'Pengaturan Parameter')
@section('page-subtitle', 'Tentukan ambang batas otomatisasi penyiraman')

@push('styles')
<style>
    .param-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
        gap: 16px;
    }
    .param-card {
        background: #FFFFFF;
        border-radius: 16px;
        padding: 24px;
        border: 1px solid #E5E0D5;
        transition: all 0.25s ease;
    }
    .param-card:hover {
        border-color: var(--text);
        transform: translateY(-4px);
    }
    .param-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #E5E0D5;
    }
    .sensor-info h3 {
        font-size: 18px;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 4px;
        font-family: 'Sora', sans-serif;
    }
    .sensor-info p {
        font-size: 13px;
        color: #888;
    }
    .mode-badge {
        padding: 6px 16px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }
    .mode-auto { background: var(--accent); color: #0D0D0D; }
    .mode-manual { background: #E5E0D5; color: #666; }

    .threshold-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 24px;
    }
    .threshold-item {
        background: #F5F0E8;
        padding: 16px;
        border-radius: 12px;
        text-align: center;
    }
    .threshold-label {
        font-size: 12px;
        color: #666;
        font-weight: 600;
        display: block;
        margin-bottom: 8px;
    }
    .threshold-range {
        font-size: 20px;
        font-weight: 800;
        color: var(--text);
        font-family: 'Sora', sans-serif;
    }
    .threshold-unit {
        font-size: 13px;
        color: #888;
        font-weight: 500;
    }

    .btn-edit-full {
        display: block;
        width: 100%;
        text-align: center;
        background: #0D0D0D;
        color: #FFFFFF;
        padding: 14px;
        border-radius: 999px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s;
    }
    .btn-edit-full:hover {
        background: #333;
    }

    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 80px 20px;
        color: #888;
    }
    .empty-state h2 { font-size: 20px; margin-bottom: 8px; color: var(--text); font-family: 'Sora', sans-serif; }

    @media (max-width: 500px) {
        .param-grid { grid-template-columns: 1fr; }
        .threshold-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="param-grid">
    @forelse($parameter as $p)
    <div class="param-card">
        <div class="param-header">
            <div class="sensor-info">
                <h3>{{ $p->sensor->nama_sensor ?? 'Sensor Tanpa Nama' }}</h3>
                <p>{{ $p->sensor->lokasi ?? 'Lokasi Umum' }}</p>
            </div>
            @php
                $modeAuto = $p->sensor->kontrolSiram->mode_auto ?? true;
            @endphp
            <span class="mode-badge {{ $modeAuto ? 'mode-auto' : 'mode-manual' }}">
                {{ $modeAuto ? 'Otomatis' : 'Manual' }}
            </span>
        </div>

        <div class="threshold-grid">
            <div class="threshold-item">
                <span class="threshold-label">Kelembapan Min</span>
                <div class="threshold-range">
                    {{ number_format($p->min_kelembapan, 0) }}<span class="threshold-unit">%</span>
                </div>
            </div>
            <div class="threshold-item">
                <span class="threshold-label">Kelembapan Maks</span>
                <div class="threshold-range">
                    {{ number_format($p->max_kelembapan, 0) }}<span class="threshold-unit">%</span>
                </div>
            </div>
            <div class="threshold-item">
                <span class="threshold-label">pH Min</span>
                <div class="threshold-range">{{ number_format($p->min_ph, 1) }}</div>
            </div>
            <div class="threshold-item">
                <span class="threshold-label">pH Maks</span>
                <div class="threshold-range">{{ number_format($p->max_ph, 1) }}</div>
            </div>
        </div>

        <a href="{{ route('parameter.edit', $p->id_parameter) }}" class="btn-edit-full">
            Ubah Pengaturan
        </a>
    </div>
    @empty
    <div class="empty-state">
        <h2>Belum ada parameter.</h2>
        <p>Silakan daftarkan sensor di menu manajemen.</p>
    </div>
    @endforelse
</div>
@endsection
