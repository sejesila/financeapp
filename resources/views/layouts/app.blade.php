<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

    {{-- PWA --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#2563eb">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Fin Tracker') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">

    <title>{{ config('app.name', 'Fin Tracker') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Dark mode script (must run before page renders) -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-ZQPW22HEMP"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-ZQPW22HEMP');
    </script>
</head>

<body class="font-sans antialiased bg-gray-100 dark:bg-gray-900 min-h-screen transition-colors duration-200">
<div class="min-h-screen">
    <!-- Session Timeout Warning Modal -->
    <div id="timeoutWarning" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center px-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-yellow-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Session Expiring Soon</h3>
            </div>
            <p class="text-base text-gray-600 dark:text-gray-300 mb-6">Your session will expire in <span id="countdown" class="font-bold text-red-600 dark:text-red-400"></span> due to inactivity.</p>
            <div class="flex space-x-3">
                <button id="stayLoggedIn" class="flex-1 bg-blue-600 text-white text-base px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    Stay Logged In
                </button>
                <button id="logoutNow" class="flex-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-base px-4 py-2 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    Logout Now
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden logout form -->
    <form id="logoutForm" method="POST" action="{{ route('logout') }}" style="display: none;">
        @csrf
    </form>

    <!-- Navigation -->
    <nav class="bg-white dark:bg-gray-800 shadow mb-6 transition-colors duration-200">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <!-- Logo with Image -->
                <a href="{{ url('/dashboard') }}" class="flex items-center space-x-3 text-gray-900 dark:text-white hover:opacity-90 transition group">
                    <div class="relative flex items-center justify-center">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg opacity-0 group-hover:opacity-100 transition duration-300 blur-md"></div>
                        <div class="relative flex items-center justify-center w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-700 rounded-lg shadow-lg group-hover:shadow-xl transition duration-300">
                            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name', 'Logo') }}" class="w-8 h-8 rounded-md object-cover">
                        </div>
                    </div>
                    <div class="hidden sm:block">
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ config('app.name', 'Fin Tracker') }}</p>

                    </div>
                </a>

                <!-- Mobile Menu Button -->
                <button id="mobileMenuBtn" class="lg:hidden text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>

                <!-- Desktop Menu -->
                <ul class="hidden lg:flex space-x-8 items-center">
                    <li>
                        <a href="{{ url('/dashboard') }}"
                           class="text-base text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('dashboard') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Dashboard
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/accounts') }}"
                           class="text-base text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('accounts*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Accounts
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/loans') }}"
                           class="text-base text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('loans*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Loans
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/transactions') }}"
                           class="text-base text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('transactions*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Transactions
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/budgets') }}"
                           class="text-base text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('budgets*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Budgets
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/reports') }}"
                           class="text-base text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('reports*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Reports
                        </a>
                    </li>

                    <!-- Dark Mode Toggle (Desktop) -->
                    <li>
                        <button id="themeToggle" class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition" title="Toggle dark mode">
                            <!-- Sun icon (shows in dark mode) -->
                            <svg id="sunIcon" class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            <!-- Moon icon (shows in light mode) -->
                            <svg id="moonIcon" class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                            </svg>
                        </button>
                    </li>

                    <!-- User Dropdown (Desktop) -->
                    <li class="relative">
                        <button id="userMenuBtn" class="text-base text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition flex items-center space-x-2 px-3 py-2">
                            <span>{{ Auth::user()->name }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg z-50 overflow-hidden">
                            <a href="{{ route('client-funds.index') }}"
                               class="block px-4 py-3 text-base text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('client-funds*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                                Client Funds
                            </a>
                            <a href="{{ route('profile.edit') }}"
                               class="block px-4 py-3 text-base text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 border-t dark:border-gray-700 transition">
                                Profile
                            </a>
                            <a href="{{ route('email-preferences.edit') }}"
                               class="flex items-center gap-2 px-4 py-3 text-base text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                Email Reports
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="block border-t dark:border-gray-700">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-3 text-base text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Mobile Menu -->
            <div id="mobileMenu" class="hidden lg:hidden mt-4">
                <ul class="space-y-2">
                    <li>
                        <a href="{{ url('/dashboard') }}"
                           class="block px-4 py-2 text-base text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('dashboard') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Dashboard
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/accounts') }}"
                           class="block px-4 py-2 text-base text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('accounts*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Accounts
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/loans') }}"
                           class="block px-4 py-2 text-base text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('loans*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Loans
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/transactions') }}"
                           class="block px-4 py-2 text-base text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('transactions*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Transactions
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/budgets') }}"
                           class="block px-4 py-2 text-base text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('budgets*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Budgets
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/reports') }}"
                           class="block px-4 py-2 text-base text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('reports*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Reports
                        </a>
                    </li>

                    <!-- Mobile User Menu -->
                    <li class="border-t dark:border-gray-700 pt-2 mt-2">
                        <div class="px-4 py-2 text-base text-gray-900 dark:text-white font-semibold">
                            {{ Auth::user()->name }}
                        </div>
                        <div class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ Auth::user()->email }}
                        </div>

                        <!-- Dark Mode Toggle (Mobile) -->
                        <button type="button" id="themeToggleMobile" class="w-full flex items-center justify-between px-4 py-2 text-base text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 rounded transition">
                            <span>Theme</span>
                            <div class="flex items-center space-x-2">
                                <span id="themeLabel" class="text-sm text-gray-500 dark:text-gray-400">
                                    <span class="dark:hidden">Light</span>
                                    <span class="hidden dark:inline">Dark</span>
                                </span>
                                <div class="p-1.5 rounded bg-gray-100 dark:bg-gray-700">
                                    <svg id="sunIconMobile" class="w-4 h-4 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                    <svg id="moonIconMobile" class="w-4 h-4 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                                    </svg>
                                </div>
                            </div>
                        </button>

                        <a href="{{ route('client-funds.index') }}"
                           class="block px-4 py-2 text-base text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('client-funds*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Client Funds
                        </a>

                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-base text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition">
                            Profile
                        </a>

                        <a href="{{ route('email-preferences.edit') }}" class="flex items-center gap-2 px-4 py-2 text-base text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Email Reports
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-base text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition">
                                Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header (optional, from slot) -->
    @isset($header)
        <header class="bg-white dark:bg-gray-800 shadow transition-colors duration-200">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
        </header>
    @endisset

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 py-6 bg-white dark:bg-gray-800 shadow rounded-lg transition-colors duration-200">
        {{ $slot }}
    </main>
</div>

<script>
    // Dark Mode Toggle Function
    function toggleTheme() {
        const html = document.documentElement;
        if (html.classList.contains('dark')) {
            html.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        } else {
            html.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
    }

    // Desktop theme toggle
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }

    // Mobile theme toggle
    const themeToggleMobile = document.getElementById('themeToggleMobile');
    if (themeToggleMobile) {
        themeToggleMobile.addEventListener('click', toggleTheme);
    }

    // Mobile menu elements
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');

    // Desktop user menu elements
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenu = document.getElementById('userMenu');

    // Mobile menu toggle
    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            mobileMenu.classList.toggle('hidden');
        });
    }

    // Desktop user menu toggle
    if (userMenuBtn && userMenu) {
        userMenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            userMenu.classList.toggle('hidden');
        });
    }

    // Close menus when clicking outside
    document.addEventListener('click', function(event) {
        // Close mobile menu if clicking outside
        if (mobileMenu && mobileMenuBtn) {
            const clickedInsideMenu = mobileMenu.contains(event.target);
            const clickedButton = mobileMenuBtn.contains(event.target);

            if (!clickedButton && !clickedInsideMenu && !mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.add('hidden');
            }
        }

        // Close desktop user menu if clicking outside
        if (userMenu && userMenuBtn) {
            if (!userMenuBtn.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        }
    });

    // Close mobile menu when a link or submit button is clicked
    if (mobileMenu) {
        const menuLinks = mobileMenu.querySelectorAll('a, button[type="submit"]');
        menuLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileMenu.classList.add('hidden');
            });
        });
    }

    // Session Timeout Management
    document.addEventListener('DOMContentLoaded', function () {
        const sessionLifetime = 120 * 60 * 1000; // 120 minutes
        const warningTime = 2 * 60 * 1000; // warn 2 minutes before expiry
        const pingInterval = 10 * 60 * 1000; // Ping server every 10 minutes
        let timeoutWarning;
        let sessionTimeout;
        let countdownInterval;
        let pingTimer;
        let lastActivity = Date.now();

        function resetTimer() {
            lastActivity = Date.now();
            clearTimeout(timeoutWarning);
            clearTimeout(sessionTimeout);
            clearInterval(countdownInterval);

            // Set warning to appear before session expires
            timeoutWarning = setTimeout(showWarning, sessionLifetime - warningTime);

            // Set actual session timeout
            sessionTimeout = setTimeout(logout, sessionLifetime);
        }

        function showWarning() {
            const modal = document.getElementById('timeoutWarning');
            if (modal) {
                modal.classList.remove('hidden');
            }

            let secondsLeft = 30;
            updateCountdown(secondsLeft);

            countdownInterval = setInterval(() => {
                secondsLeft--;
                updateCountdown(secondsLeft);

                if (secondsLeft <= 0) {
                    clearInterval(countdownInterval);
                    logout();
                }
            }, 1000);
        }

        function updateCountdown(seconds) {
            const countdownElement = document.getElementById('countdown');
            if (countdownElement) {
                const minutes = Math.floor(seconds / 60);
                const secs = seconds % 60;
                countdownElement.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;
            }
        }

        function hideWarning() {
            clearInterval(countdownInterval);
            const modal = document.getElementById('timeoutWarning');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function logout() {
            clearTimeout(timeoutWarning);
            clearTimeout(sessionTimeout);
            clearInterval(countdownInterval);
            clearInterval(pingTimer);

            const logoutForm = document.getElementById('logoutForm');
            if (logoutForm) {
                logoutForm.submit();
            }
        }

        function stayLoggedIn() {
            hideWarning();
            pingServer(true);
        }

        function pingServer(userInitiated = false) {
            @if(Route::has('session.ping'))
            fetch('{{ route('session.ping') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(response => {
                    if (response.status === 401 || response.status === 419) {
                        logout();
                    } else if (userInitiated) {
                        resetTimer();
                    }
                })
                .catch(error => {
                    console.error('Session ping failed:', error);
                    if (userInitiated) {
                        resetTimer();
                    }
                });
            @else
            if (userInitiated) {
                resetTimer();
            }
            @endif
        }

        // Track user activity
        const events = ['mousedown', 'keypress', 'scroll', 'touchstart', 'click'];
        events.forEach(event => {
            document.addEventListener(event, () => {
                const now = Date.now();
                if (now - lastActivity > 10000) {
                    resetTimer();
                }
            }, { passive: true });
        });

        // Button event listeners
        const stayLoggedInBtn = document.getElementById('stayLoggedIn');
        if (stayLoggedInBtn) {
            stayLoggedInBtn.addEventListener('click', stayLoggedIn);
        }

        const logoutNowBtn = document.getElementById('logoutNow');
        if (logoutNowBtn) {
            logoutNowBtn.addEventListener('click', logout);
        }

        // Periodic ping to keep session alive
        setTimeout(() => {
            pingTimer = setInterval(() => {
                pingServer(false);
            }, pingInterval);
        }, pingInterval);

        // Initialize timers
        resetTimer();

        // Handle fetch errors
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            return originalFetch.apply(this, args).then(response => {
                if (response.status === 419) {
                    if (confirm('Your session has expired. Reload the page to continue?')) {
                        window.location.reload();
                    } else {
                        logout();
                    }
                }
                return response;
            }).catch(error => {
                return Promise.reject(error);
            });
        };
    });
</script>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .then(r => console.log('[PWA] SW registered:', r.scope))
                .catch(e => console.error('[PWA] SW failed:', e));
        });
    }
</script>
</body>
</html>
