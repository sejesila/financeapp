<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #dbeafe;
            --accent: #f59e0b;
            --bg-light: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 20s ease-in-out infinite;
            z-index: 1;
        }

        body::after {
            content: '';
            position: fixed;
            bottom: -20%;
            left: -5%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.06) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 25s ease-in-out infinite reverse;
            z-index: 1;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) translateX(0px); }
            50% { transform: translateY(-30px) translateX(20px); }
        }

        .container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 460px;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 3rem;
            animation: slideInDown 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .logo-wrapper {
            display: inline-block;
            position: relative;
            margin-bottom: 1.5rem;
        }

        .logo-wrapper img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            filter: drop-shadow(0 10px 25px rgba(37, 99, 235, 0.15));
            transition: transform 0.3s ease;
        }

        .logo-wrapper:hover img {
            transform: scale(1.05) translateY(-5px);
        }

        .logo-ring {
            position: absolute;
            inset: -8px;
            border: 2px solid rgba(37, 99, 235, 0.2);
            border-radius: 50%;
            animation: spin 20s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .logo-section h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .logo-section p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(37, 99, 235, 0.1);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.05);
            animation: slideInUp 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .info-section {
            background: rgba(37, 99, 235, 0.05);
            border: 1px solid rgba(37, 99, 235, 0.1);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            animation: slideInUp 0.6s ease;
        }

        .info-section strong {
            color: var(--text-primary);
            display: block;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .status-message {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #059669;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            animation: slideInDown 0.6s ease;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            animation: slideInUp 0.6s ease 0.2s backwards;
        }

        .btn-primary {
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: left 0.4s ease;
            z-index: 1;
        }

        .btn-primary:hover::before { left: 100%; }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:active { transform: translateY(0); }

        .btn-primary span {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logout-link {
            text-align: center;
        }

        .logout-link button {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: color 0.3s ease;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .logout-link button:hover {
            color: var(--primary);
        }

        @media (max-width: 640px) {
            .card { padding: 2rem; }
            .logo-section h1 { font-size: 1.5rem; }
            body::before { width: 400px; height: 400px; }
            body::after { width: 350px; height: 350px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="logo-section">
        <div class="logo-wrapper">
            <div class="logo-ring"></div>
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name', 'Logo') }}">
        </div>
        <h1>{{ config('app.name', 'Laravel') }}</h1>
        <p>Verify your email address</p>
    </div>

    <div class="card">
        <div class="info-section">
            <strong>Thanks for signing up!</strong>
            Before getting started, please verify your email address by clicking on the link we just emailed to you. If you didn't receive the email, we'll gladly send you another.
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="status-message">
                A new verification link has been sent to the email address you provided during registration.
            </div>
        @endif

        <div class="action-buttons">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn-primary">
                    <span>Resend Verification Email</span>
                </button>
            </form>

            <div class="logout-link">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Log Out</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
