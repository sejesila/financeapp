<!DOCTYPE html>
<html>
<head>
    <title>Smart</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">

<nav class="bg-white shadow mb-6">
    <div class="container mx-auto px-4 py-4 flex justify-between items-center">
        <a href="{{ url('/dashboard') }}" class="flex items-center space-x-2 text-gray-900 hover:opacity-80 transition">
            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </a>

        <ul class="flex space-x-8">
            <li>
                <a href="{{ url('/dashboard') }}"
                   class="text-gray-700 hover:text-blue-600 transition {{ request()->is('dashboard') ? 'text-blue-600 font-semibold' : '' }}">
                    Dashboardnnn
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
        </ul>
    </div>
</nav>

<!-- Container -->
<div class="w-full px-6 py-6 bg-white shadow rounded-lg">
    @yield('content')
</div>

</body>
</html>
