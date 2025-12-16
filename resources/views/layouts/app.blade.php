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
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <a href="{{ url('/dashboard') }}" class="text-xl font-bold text-gray-900">My Budget App</a>

            <ul class="flex space-x-8 items-center">
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

                <!-- User Dropdown -->
                <li class="relative group">
                    <button class="text-gray-700 hover:text-blue-600 transition flex items-center space-x-2 px-3 py-2">
                        <span>{{ Auth::user()->name }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                        </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div class="absolute right-0 mt-0 w-48 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition duration-200 z-50">
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-3 text-gray-700 hover:bg-gray-100 text-sm border-b first:rounded-t-lg">
                            Profile
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="block">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-3 text-gray-700 hover:bg-gray-100 text-sm last:rounded-b-lg">
                                Logout
                            </button>
                        </form>
                    </div>
                </li>
            </ul>
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
    <main class="w-full px-6 py-6 bg-white shadow rounded-lg">
        {{ $slot }}
    </main>
</div>
</body>
</html>
