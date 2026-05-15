@extends('layouts.app')

@section('title', 'History Kelembapan')
@section('page-title', 'History Kelembapan')
@section('page-subtitle', 'Riwayat data kelembapan tanah dari sensor')

@push('styles')
<style>
    .data-card {
        background: #FFFFFF;
        border-radius: 16px;
        padding: 24px;
        border: 1px solid #E5E0D5;
        overflow-x: auto;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 600px;
    }
    .data-table th, .data-table td {
        padding: 16px;
        text-align: left;
        border-bottom: 1px solid #E5E0D5;
    }
    .data-table th {
        font-weight: 700;
        color: #888;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .data-table td {
        font-size: 14px;
        color: var(--text);
    }
    .sensor-badge {
        background: #F5F0E8;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
    }
    .val-badge {
        font-weight: 700;
        font-family: 'Sora', sans-serif;
    }
    .val-low { color: #D97706; }
    .val-ok { color: #65A30D; }
    .val-high { color: #0284C7; }
</style>
@endpush

@section('content')
<div class="data-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Sensor</th>
                <th>Kelembapan (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($history as $item)
                @php
                    $k = $item->kelembapan;
                    $class = $k < 30 ? 'val-low' : ($k > 70 ? 'val-high' : 'val-ok');
                @endphp
                <tr>
                    <td>{{ $item->created_at->format('d M Y, H:i') }}</td>
                    <td>
                        <span class="sensor-badge">{{ $item->sensor->nama_sensor ?? 'Unknown' }}</span>
                    </td>
                    <td>
                        <span class="val-badge {{ $class }}">{{ number_format($k, 1) }}%</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align:center; padding: 40px; color:#888;">Belum ada data history kelembapan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div style="margin-top: 20px;">
        {{ $history->links() }}
    </div>
</div>
@endsection
