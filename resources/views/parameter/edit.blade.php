@extends('layouts.app')

@section('title', 'Ubah Parameter Sensor')
@section('page-title', 'Ubah Parameter Sensor')
@section('page-subtitle', 'Sensor: {{ $sensor->nama_sensor ?? "" }}')

@push('styles')
<style>
    .form-container {
        max-width: 800px;
    }
    .config-card {
        background: #FFFFFF;
        border-radius: 16px;
        padding: 32px;
        border: 1px solid #E5E0D5;
    }
    .config-section {
        margin-bottom: 32px;
    }
    .config-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 12px;
        border-bottom: 1px solid #E5E0D5;
        font-family: 'Sora', sans-serif;
    }

    .slider-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .slider-item {
        background: #F5F0E8;
        padding: 20px;
        border-radius: 12px;
    }
    .slider-label {
        font-size: 14px;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 12px;
        display: block;
    }
    .slider-hint {
        font-size: 12px;
        color: #888;
        margin-top: 8px;
    }
    .slider-input {
        width: 100%;
        height: 8px;
        background: #E5E0D5;
        border-radius: 999px;
        outline: none;
        -webkit-appearance: none;
        cursor: pointer;
    }
    .slider-input::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 24px;
        height: 24px;
        background: var(--text);
        border: 4px solid #fff;
        border-radius: 50%;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .slider-input::-moz-range-thumb {
        width: 24px;
        height: 24px;
        background: var(--text);
        border: 4px solid #fff;
        border-radius: 50%;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .slider-display {
        display: inline-block;
        margin-top: 12px;
        font-size: 24px;
        font-weight: 800;
        color: var(--text);
        background: #fff;
        padding: 6px 16px;
        border-radius: 10px;
        font-family: 'Sora', sans-serif;
    }

    .mode-selector {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .mode-option {
        position: relative;
    }
    .mode-option input {
        position: absolute;
        opacity: 0;
    }
    .mode-option label {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 24px 20px;
        background: #FFFFFF;
        border: 2px solid #E5E0D5;
        border-radius: 16px;
        cursor: pointer;
        transition: 0.2s;
        gap: 8px;
    }
    .mode-option input:checked + label {
        border-color: var(--text);
        background: var(--accent);
        box-shadow: 0 8px 24px rgba(200, 241, 53, 0.2);
    }
    .mode-option label:hover {
        border-color: var(--text);
    }
    .mode-icon { font-size: 28px; }
    .mode-name { font-size: 16px; font-weight: 700; color: var(--text); }
    .mode-desc { font-size: 12px; color: #666; }

    .action-btns {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 16px;
        margin-top: 32px;
    }
    .btn-save {
        background: #0D0D0D;
        color: #FFFFFF;
        padding: 16px;
        border: none;
        border-radius: 999px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
        transition: 0.2s;
    }
    .btn-save:hover {
        background: #333;
    }
    .btn-back {
        background: #F5F0E8;
        color: var(--text);
        padding: 16px;
        border-radius: 999px;
        text-decoration: none;
        text-align: center;
        font-weight: 700;
        font-size: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
    }
    .btn-back:hover { background: #E5E0D5; }

    .error-msg {
        color: #D97706;
        font-size: 13px;
        margin-top: 8px;
    }

    @media (max-width: 600px) {
        .slider-grid, .mode-selector, .action-btns { grid-template-columns: 1fr; }
        .config-card { padding: 24px; }
    }
</style>
@endpush

@section('content')
<div class="form-container">
    <div class="config-card">
        <form action="{{ route('parameter.update', $parameter->id_parameter) }}" method="POST">
            @csrf
            @method('PATCH')

            <div class="config-section">
                <div class="config-title">Ambang Kelembapan</div>
                <div class="slider-grid">
                    <div class="slider-item">
                        <label class="slider-label" for="min_k">Batas Minimum (Mulai Siram)</label>
                        <input type="range" name="min_kelembapan" id="min_k" class="slider-input"
                               min="0" max="100"
                               value="{{ old('min_kelembapan', $parameter->min_kelembapan) }}"
                               oninput="updateText('min_k', 'min_k_val', '%')">
                        <span class="slider-display" id="min_k_val">{{ number_format($parameter->min_kelembapan, 0) }}%</span>
                        <div class="slider-hint">Pompa menyala saat kelembapan di bawah ini</div>
                        @error('min_kelembapan')<div class="error-msg">{{ $message }}</div>@enderror
                    </div>
                    <div class="slider-item">
                        <label class="slider-label" for="max_k">Batas Maksimum (Berhenti)</label>
                        <input type="range" name="max_kelembapan" id="max_k" class="slider-input"
                               min="0" max="100"
                               value="{{ old('max_kelembapan', $parameter->max_kelembapan) }}"
                               oninput="updateText('max_k', 'max_k_val', '%')">
                        <span class="slider-display" id="max_k_val">{{ number_format($parameter->max_kelembapan, 0) }}%</span>
                        <div class="slider-hint">Pompa berhenti saat kelembapan di atas ini</div>
                        @error('max_kelembapan')<div class="error-msg">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="config-section">
                <div class="config-title">Rentang pH Tanah</div>
                <div class="slider-grid">
                    <div class="slider-item">
                        <label class="slider-label" for="min_p">pH Minimum Aman</label>
                        <input type="range" name="min_ph" id="min_p" class="slider-input"
                               min="0" max="14" step="0.1"
                               value="{{ old('min_ph', $parameter->min_ph) }}"
                               oninput="updateText('min_p', 'min_p_val', '')">
                        <span class="slider-display" id="min_p_val">{{ number_format($parameter->min_ph, 1) }}</span>
                        @error('min_ph')<div class="error-msg">{{ $message }}</div>@enderror
                    </div>
                    <div class="slider-item">
                        <label class="slider-label" for="max_p">pH Maksimum Aman</label>
                        <input type="range" name="max_ph" id="max_p" class="slider-input"
                               min="0" max="14" step="0.1"
                               value="{{ old('max_ph', $parameter->max_ph) }}"
                               oninput="updateText('max_p', 'max_p_val', '')">
                        <span class="slider-display" id="max_p_val">{{ number_format($parameter->max_ph, 1) }}</span>
                        @error('max_ph')<div class="error-msg">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>



            <div style="margin-top:20px; font-size:13px; color:#888;">
                Nilai default: Min 40%, Max 70% — sesuaikan kondisi bibit pepaya
                <!-- // HARDCODE: move this note text to lang file or config in future -->
            </div>

            <div class="action-btns">
                <button type="submit" class="btn-save">Simpan Pengaturan</button>
                <a href="{{ route('parameter.index') }}" class="btn-back">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function updateText(inputId, displayId, suffix) {
    document.getElementById(displayId).innerText = document.getElementById(inputId).value + suffix;
}
</script>
@endpush
