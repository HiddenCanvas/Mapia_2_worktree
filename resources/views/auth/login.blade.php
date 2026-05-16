<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — MAPIA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --text: #0D0D0D;
            --background: #F5F0E8;
            --primary: #0D0D0D;
            --secondary: #FFFFFF;
            --accent: #C8F135;
            --accent-hover: #b8e228;
            --glass: rgba(255, 255, 255, 0.9);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            background: #0D0D0D;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }

        /* Abstract backgrounds */
        .bg-blob {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(200, 241, 53, 0.15) 0%, rgba(0,0,0,0) 70%);
            border-radius: 50%;
            z-index: 0;
            filter: blur(40px);
        }
        .blob-1 { top: -100px; left: -100px; }
        .blob-2 { bottom: -100px; right: -100px; animation: float 10s infinite alternate; }

        @keyframes float {
            from { transform: translate(0, 0); }
            to { transform: translate(-50px, -20px); }
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 10;
        }

        .login-card {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            padding: 56px 40px;
            box-shadow: 0 40px 100px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.1);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-logo {
            font-size: 56px;
            margin-bottom: 16px;
            display: inline-block;
            filter: drop-shadow(0 4px 10px rgba(200, 241, 53, 0.3));
        }

        .login-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 8px;
            font-family: 'Sora', sans-serif;
            letter-spacing: -1px;
        }

        .login-subtitle {
            font-size: 15px;
            color: #666;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #555;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #E5E0D5;
            border-radius: 14px;
            font-size: 15px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            background: #fff;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(13,13,13,0.05);
        }

        .btn-login {
            width: 100%;
            padding: 18px;
            background: var(--accent);
            color: #0D0D0D;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 800;
            font-family: 'Sora', sans-serif;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(200, 241, 53, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert-error {
            background: #FFF4F2;
            border: 1px solid #FFD0CA;
            color: #D32F2F;
            padding: 16px;
            border-radius: 14px;
            margin-bottom: 28px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .login-footer {
            text-align: center;
            margin-top: 32px;
            font-size: 13px;
            color: rgba(255,255,255,0.4);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card { padding: 40px 24px; }
            .login-title { font-size: 28px; }
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            transition: all 0.2s ease;
            z-index: 5;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        .form-input-password {
            padding-right: 52px !important;
        }
    </style>
</head>
<body>
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">🌿</div>
                <h1 class="login-title">MAPIA</h1>
                <p class="login-subtitle">Masuk untuk mengelola sistem irigasi<br>kebun pepaya Anda secara otomatis.</p>
            </div>

            @if($errors->any())
            <div class="alert-error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                Email atau kata sandi tidak sesuai.
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <div class="input-wrapper">
                        <input
                            type="email"
                            name="email"
                            class="form-input"
                            value="{{ old('email') }}"
                            placeholder="nama@email.com"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Kata Sandi</label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="form-input form-input-password"
                            placeholder="••••••••"
                            required
                        >
                        <button type="button" class="toggle-password" id="togglePassword" tabindex="-1">
                            <svg id="eyeIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg id="eyeOffIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    Masuk Sekarang
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
            </form>
        </div>
        <div class="login-footer">
            &copy; {{ date('Y') }} MAPIA Intelligence &bull; Sustainable Agriculture
        </div>
    </div>
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');
        const eyeIcon = document.querySelector('#eyeIcon');
        const eyeOffIcon = document.querySelector('#eyeOffIcon');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            if (type === 'password') {
                eyeIcon.style.display = 'block';
                eyeOffIcon.style.display = 'none';
            } else {
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'block';
            }
        });
    </script>
</body>
</html>
