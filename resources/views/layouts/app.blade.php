<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MAPIA — @yield('title', 'Dashboard')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
<meta name="csrf-token" content="{{ csrf_token() }}">
@stack('styles')
<style>
:root{--text:#0D0D0D;--background:#F5F0E8;--primary:#0D0D0D;--secondary:#FFFFFF;--accent:#C8F135;--border:#E5E0D5;--sidebar-w:240px;--topbar-h:70px}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:16px;height:100%}
body{font-family:'DM Sans',sans-serif;background:var(--background);color:var(--text);min-height:100vh;display:flex;font-size:15px;line-height:1.5;overflow:hidden}
h1,h2,h3,h4,h5,h6,.page-title{font-family:'Sora',sans-serif}

/* ── SIDEBAR ── */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--primary);display:flex;flex-direction:column;z-index:200;transition:transform .3s ease}
.sidebar-logo{padding:24px 20px 20px;border-bottom:1px solid rgba(255,255,255,.05)}
.sidebar-logo a{display:flex;align-items:center;gap:10px;text-decoration:none;color:#fff;font-size:22px;font-weight:700;letter-spacing:-.3px;font-family:'Sora',sans-serif}
.sidebar-logo span{font-size:26px}
.sidebar-nav{flex:1;padding:20px 0;overflow-y:auto}
.nav-label{font-size:11px;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:1px;padding:12px 20px 8px}
.nav-item{display:flex;align-items:center;gap:12px;padding:14px 20px;color:#888;text-decoration:none;font-size:15px;font-weight:500;border-left:3px solid transparent;transition:all .2s;cursor:pointer;min-height:48px}
.nav-item:hover{background:rgba(255,255,255,.03);color:#fff}
.nav-item.active{background:rgba(200,241,53,.1);color:var(--accent);border-left-color:var(--accent)}
.nav-item .icon{font-size:20px;min-width:24px;text-align:center}
.nav-badge{margin-left:auto;background:var(--accent);color:#0D0D0D;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;min-width:20px;text-align:center}
.sidebar-footer{padding:20px;border-top:1px solid rgba(255,255,255,.05)}
.sidebar-user{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.user-avatar{width:40px;height:40px;border-radius:50%;background:#333;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:16px;flex-shrink:0;overflow:hidden}
.user-avatar img{width:100%;height:100%;object-fit:cover}
.user-info{flex:1;min-width:0}
.user-name{color:#fff;font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-family:'Sora',sans-serif}
.user-role{color:#888;font-size:12px}
.btn-logout{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px;background:rgba(255,255,255,.05);color:#fff;border:none;border-radius:999px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;transition:all .2s;min-height:44px}
.btn-logout:hover{background:rgba(255,255,255,.1)}

/* ── TOPBAR (mobile) ── */
.topbar{display:none;position:fixed;top:0;left:0;right:0;height:var(--topbar-h);background:var(--primary);align-items:center;justify-content:space-between;padding:0 20px;z-index:150;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.topbar-logo{color:#fff;font-size:20px;font-weight:700;display:flex;align-items:center;gap:8px;font-family:'Sora',sans-serif}
.hamburger{background:none;border:none;cursor:pointer;padding:8px;color:#fff;font-size:24px;min-height:44px;min-width:44px;display:flex;align-items:center;justify-content:center;border-radius:6px}
.hamburger:hover{background:rgba(255,255,255,.1)}

/* ── MAIN CONTENT ── */
.main-wrap{margin-left:var(--sidebar-w);height:100vh;display:flex;flex-direction:column;flex:1;overflow-y:auto;overflow-x:hidden}
.page-header{background:transparent;padding:32px 36px 16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;flex-shrink:0}
.page-title{font-size:28px;font-weight:700;color:var(--text);letter-spacing:-0.5px}
.page-subtitle{font-size:15px;color:#666;margin-top:6px}
.page-body{padding:16px 36px 40px;flex:1}

/* ── OVERLAY ── */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:190;backdrop-filter:blur(4px)}
.sidebar-overlay.show{display:block}

@media(max-width:767px){
.topbar{display:flex}
.sidebar{transform:translateX(-100%)}
.sidebar.open{transform:translateX(0)}
.main-wrap{margin-left:0;padding-top:var(--topbar-h);height:auto;min-height:calc(100vh - var(--topbar-h));overflow:visible}
.page-header{padding:24px 20px 16px}
.page-body{padding:16px 20px 32px}
body,html{overflow:auto;height:auto}
}
</style>
</head>
<body>

{{-- Mobile Topbar --}}
<div class="topbar">
    <div class="topbar-logo">MAPIA</div>
    <button class="hamburger" id="menuBtn" aria-label="Buka menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
    </button>
</div>
<div class="sidebar-overlay" id="overlay"></div>

{{-- Sidebar --}}
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <a href="{{ route('dashboard') }}">MAPIA</a>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Menu Utama</div>
        <a href="{{ route('dashboard') }}" class="nav-item {{ Request::routeIs('dashboard*') ? 'active' : '' }}">
            <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg></span> Dashboard
        </a>
        <a href="{{ route('monitoring.kontrol') }}" class="nav-item {{ Request::routeIs('monitoring*') ? 'active' : '' }}">
            <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg></span> Kontrol Siram
        </a>
        <a href="{{ route('parameter.index') }}" class="nav-item {{ Request::routeIs('parameter*') ? 'active' : '' }}">
            <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg></span> Parameter
        </a>
        <a href="{{ route('riwayat.index') }}" class="nav-item {{ Request::routeIs('riwayat*') ? 'active' : '' }}">
            <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg></span> Riwayat
        </a>
        <a href="{{ route('history-kelembapan.index') }}" class="nav-item {{ Request::routeIs('history-kelembapan*') ? 'active' : '' }}">
            <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg></span> History Kelembapan
        </a>
        <a href="{{ route('notifikasi.index') }}" class="nav-item {{ Request::routeIs('notifikasi*') ? 'active' : '' }}">
            <span class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg></span> Notifikasi
            @if(isset($unreadCount) && $unreadCount > 0)
                <span class="nav-badge">{{ $unreadCount }}</span>
            @endif
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">
                @if(Auth::user()->foto ?? null)
                    <img src="{{ asset('storage/'.Auth::user()->foto) }}" alt="Foto">
                @else
                    {{ strtoupper(substr(Auth::user()->nama ?? 'U', 0, 1)) }}
                @endif
            </div>
            <div class="user-info">
                <div class="user-name">{{ Auth::user()->nama ?? 'Pengguna' }}</div>
                <div class="user-role">Petani</div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-logout"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Keluar</button>
        </form>
    </div>
</aside>

{{-- Main --}}
<div class="main-wrap">
    <div class="page-header">
        <div>
            <div class="page-title">@yield('page-title', 'Dashboard')</div>
            <div class="page-subtitle">@yield('page-subtitle', '')</div>
        </div>
        <div>@yield('page-actions')</div>
    </div>
    <div class="page-body">
        @if(session('success'))
        <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:15px;">
            [SUKSES] {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:15px;">
            [ERROR] {{ session('error') }}
        </div>
        @endif
        @yield('content')
    </div>
</div>

<script>
const menuBtn=document.getElementById('menuBtn');
const sidebar=document.getElementById('sidebar');
const overlay=document.getElementById('overlay');
function toggleSidebar(){sidebar.classList.toggle('open');overlay.classList.toggle('show')}
menuBtn&&menuBtn.addEventListener('click',toggleSidebar);
overlay&&overlay.addEventListener('click',toggleSidebar);
</script>
@stack('scripts')
</body>
</html>
