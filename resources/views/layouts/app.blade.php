<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Budget App') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Dark mode script (must run before page renders) -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
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
            <p class="text-gray-600 dark:text-gray-300 mb-6">Your session will expire in <span id="countdown" class="font-bold text-red-600 dark:text-red-400"></span> due to inactivity.</p>
            <div class="flex space-x-3">
                <button id="stayLoggedIn" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    Stay Logged In
                </button>
                <button id="logoutNow" class="flex-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    Logout Now
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="bg-white dark:bg-gray-800 shadow mb-6 transition-colors duration-200">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <!-- Logo -->
                <a href="{{ url('/dashboard') }}" class="flex items-center space-x-2 text-gray-900 dark:text-white hover:opacity-80 transition">
                    <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </a>

                <div class="flex items-center space-x-4">
                    <!-- Dark Mode Toggle -->
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

                    <!-- Mobile Menu Button -->
                    <button id="mobileMenuBtn" class="lg:hidden text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>

                <!-- Desktop Menu -->
                <ul class="hidden lg:flex space-x-8 items-center">
                    <li>
                        <a href="{{ url('/dashboard') }}"
                           class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('dashboard') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Dashboard
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/accounts') }}"
                           class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('accounts*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Accounts
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/loans') }}"
                           class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('loans*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Loans
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/transactions') }}"
                           class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('transactions*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Transactions
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/budgets') }}"
                           class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('budgets*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Budgets
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/reports') }}"
                           class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('reports*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Reports
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/categories') }}"
                           class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->is('categories*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Categories
                        </a>
                    </li>

                    <!-- User Dropdown (Desktop) -->
                    <li class="relative">
                        <button id="userMenuBtn" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition flex items-center space-x-2 px-3 py-2">
                            <span>{{ Auth::user()->name }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg z-50">
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm border-b dark:border-gray-700 rounded-t-lg transition">
                                Profile
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="block">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm rounded-b-lg transition">
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
                           class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('dashboard') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Dashboard
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/accounts') }}"
                           class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('accounts*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Accounts
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/loans') }}"
                           class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('loans*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Loans
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/transactions') }}"
                           class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('transactions*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Transactions
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/budgets') }}"
                           class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('budgets*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Budgets
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/reports') }}"
                           class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('reports*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Reports
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/categories') }}"
                           class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition {{ request()->is('categories*') ? 'bg-blue-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400 font-semibold' : '' }}">
                            Categories
                        </a>
                    </li>

                    <!-- Mobile User Menu -->
                    <li class="border-t dark:border-gray-700 pt-2">
                        <div class="px-4 py-2 text-gray-900 dark:text-white font-semibold">
                            {{ Auth::user()->name }}
                        </div>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition">
                            Profile
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400 rounded transition">
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
    // Dark Mode Toggle
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;

    themeToggle.addEventListener('click', function() {
        if (html.classList.contains('dark')) {
            html.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        } else {
            html.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
    });

    // Mobile menu toggle
    document.getElementById('mobileMenuBtn').addEventListener('click', function() {
        const menu = document.getElementById('mobileMenu');
        menu.classList.toggle('hidden');
    });

    // Desktop user menu toggle
    document.getElementById('userMenuBtn').addEventListener('click', function() {
        const menu = document.getElementById('userMenu');
        menu.classList.toggle('hidden');
    });

    // Close desktop user menu when clicking outside
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('userMenu');
        const btn = document.getElementById('userMenuBtn');
        if (!btn.contains(event.target) && !menu.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });

    // Session Timeout Management
    (function() {
        const sessionLifetime = {{ config('session.lifetime') }} * 60 * 1000; // Convert to milliseconds
        const warningTime = 5 * 60 * 1000; // Show warning 5 minutes before expiry
        const pingInterval = 5 * 60 * 1000; // Ping every 5 minutes
        let timeoutWarning;
        let sessionTimeout;
        let countdownInterval;
        let pingTimer;
        let lastActivity = Date.now();

        function resetTimer() {
            lastActivity = Date.now();
            clearTimeout(timeoutWarning);
            clearTimeout(sessionTimeout);

            // Show warning before session expires
            timeoutWarning = setTimeout(showWarning, sessionLifetime - warningTime);

            // Auto logout when session expires
            sessionTimeout = setTimeout(logout, sessionLifetime);
        }

        function showWarning() {
            const modal = document.getElementById('timeoutWarning');
            modal.classList.remove('hidden');

            let secondsLeft = 300; // 5 minutes
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
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            document.getElementById('countdown').textContent =
                `${minutes}:${secs.toString().padStart(2, '0')}`;
        }

        function hideWarning() {
            clearInterval(countdownInterval);
            document.getElementById('timeoutWarning').classList.add('hidden');
        }

        function logout() {
            clearTimeout(timeoutWarning);
            clearTimeout(sessionTimeout);
            clearInterval(countdownInterval);
            clearInterval(pingTimer);
            window.location.href = '{{ route('session.expired') }}';
        }

        function stayLoggedIn() {
            hideWarning();
            pingServer(true);
        }

        function pingServer(userInitiated = false) {
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
                    if (response.status === 401) {
                        logout();
                    } else if (userInitiated) {
                        resetTimer();
                    }
                })
                .catch(error => {
                    console.error('Session ping failed:', error);
                });
        }

        // Event listeners for user activity
        const events = ['mousedown', 'keypress', 'scroll', 'touchstart', 'click'];
        events.forEach(event => {
            document.addEventListener(event, () => {
                const now = Date.now();
                // Only reset if it's been more than 10 seconds since last activity
                if (now - lastActivity > 10000) {
                    resetTimer();
                }
            }, { passive: true });
        });

        // Button handlers
        document.getElementById('stayLoggedIn')?.addEventListener('click', stayLoggedIn);
        document.getElementById('logoutNow')?.addEventListener('click', logout);

        // Start periodic ping to keep session alive
        pingTimer = setInterval(() => {
            pingServer(false);
        }, pingInterval);

        // Initialize timer and do initial ping
        resetTimer();
        pingServer(false);

        // Handle 419 CSRF errors globally
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            return originalFetch.apply(this, args).then(response => {
                if (response.status === 419) {
                    // CSRF token mismatch
                    if (confirm('Your session has expired. Reload the page to continue?')) {
                        window.location.reload();
                    } else {
                        window.location.href = '{{ route('login') }}';
                    }
                }
                return response;
            });
        };
    })();
</script>
</body>
</html>
