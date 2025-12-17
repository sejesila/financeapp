<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Budget App') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gray-100 min-h-screen">
<div class="min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow mb-6">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <!-- Logo -->
                <a href="{{ url('/dashboard') }}" class="flex items-center space-x-2 text-gray-900 hover:opacity-80 transition">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </a>

                <!-- Mobile Menu Button -->
                <button id="mobileMenuBtn" class="lg:hidden text-gray-700 hover:text-blue-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>

                <!-- Desktop Menu -->
                <ul class="hidden lg:flex space-x-8 items-center">
                    <li>
                        <a href="{{ url('/dashboard') }}"
                           class="text-gray-700 hover:text-blue-600 transition {{ request()->is('dashboard') ? 'text-blue-600 font-semibold' : '' }}">
                            Dashboard
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/accounts') }}"
                           class="text-gray-700 hover:text-blue-600 transition {{ request()->is('accounts*') ? 'text-blue-600 font-semibold' : '' }}">
                            Accounts
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/loans') }}"
                           class="text-gray-700 hover:text-blue-600 transition {{ request()->is('loans*') ? 'text-blue-600 font-semibold' : '' }}">
                            Loans
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/transactions') }}"
                           class="text-gray-700 hover:text-blue-600 transition {{ request()->is('transactions*') ? 'text-blue-600 font-semibold' : '' }}">
                            Transactions
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/budgets') }}"
                           class="text-gray-700 hover:text-blue-600 transition {{ request()->is('budgets*') ? 'text-blue-600 font-semibold' : '' }}">
                            Budgets
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/reports') }}"
                           class="text-gray-700 hover:text-blue-600 transition {{ request()->is('reports*') ? 'text-blue-600 font-semibold' : '' }}">
                            Reports
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/categories') }}"
                           class="text-gray-700 hover:text-blue-600 transition {{ request()->is('categories*') ? 'text-blue-600 font-semibold' : '' }}">
                            Categories
                        </a>
                    </li>

                    <!-- User Dropdown (Desktop) -->
                    <li class="relative">
                        <button id="userMenuBtn" class="text-gray-700 hover:text-blue-600 transition flex items-center space-x-2 px-3 py-2">
                            <span>{{ Auth::user()->name }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-50">
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-3 text-gray-700 hover:bg-gray-100 text-sm border-b rounded-t-lg">
                                Profile
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="block">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-3 text-gray-700 hover:bg-gray-100 text-sm rounded-b-lg">
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
                           class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded transition {{ request()->is('dashboard') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">
                            Dashboard
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/accounts') }}"
                           class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded transition {{ request()->is('accounts*') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">
                            Accounts
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/loans') }}"
                           class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded transition {{ request()->is('loans*') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">
                            Loans
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/transactions') }}"
                           class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded transition {{ request()->is('transactions*') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">
                            Transactions
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/budgets') }}"
                           class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded transition {{ request()->is('budgets*') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">
                            Budgets
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/reports') }}"
                           class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded transition {{ request()->is('reports*') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">
                            Reports
                        </a>
                    </li>

                    <li>
                        <a href="{{ url('/categories') }}"
                           class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded transition {{ request()->is('categories*') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">
                            Categories
                        </a>
                    </li>

                    <!-- Mobile User Menu -->
                    <li class="border-t pt-2">
                        <div class="px-4 py-2 text-gray-900 font-semibold">
                            {{ Auth::user()->name }}
                        </div>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded transition">
                            Profile
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded transition">
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
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
        </header>
    @endisset

    <!-- Main Content -->
    <main class="w-full px-4 sm:px-6 py-6 bg-white shadow rounded-lg">
        {{ $slot }}
    </main>
</div>

<script>
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
</script>
</body>
</html>
