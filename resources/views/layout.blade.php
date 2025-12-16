<!DOCTYPE html>
<html>
<head>
    <title>Budget App</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">

<nav class="bg-white shadow mb-6">
    <div class="container mx-auto px-4 py-4 flex justify-between items-center">
        <a href="{{ url('/dashboard') }}" class="text-xl font-bold text-gray-900">My Budget App</a>

        <ul class="flex space-x-8">
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
        </ul>
    </div>
</nav>

<!-- Container -->
<div class="w-full px-6 py-6 bg-white shadow rounded-lg">
    @yield('content')
</div>

</body>
</html>
