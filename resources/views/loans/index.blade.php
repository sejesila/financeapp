<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Loans') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Filters -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <p class="text-gray-600 text-sm">
                    Loans are automatically created when you top up your account via M-Pesa
                </p>

                <form method="GET" action="{{ route('loans.index') }}" class="flex flex-wrap gap-2 items-center">
                    <div class="relative">
                        <select name="year" class="block appearance-none w-full border border-gray-300 p-2 pl-3 pr-8 rounded text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Years</option>
                            @for($y = $minYear; $y <= $maxYear; $y++)
                                <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                            @endfor
                        </select>

                    </div>

                    <div class="relative">
                        <select name="filter" class="block appearance-none w-full border border-gray-300 p-2 pl-3 pr-8 rounded text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            <option value="active" @selected($filter == 'active')>Active Loans</option>
                            <option value="all_paid" @selected($filter == 'all_paid')>All Paid Loans</option>
                        </select>

                    </div>

                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">
                        Filter
                    </button>

                    @if($year || $filter != 'active')
                        <a href="{{ route('loans.index') }}" class="text-gray-600 text-sm hover:text-gray-800">
                            Clear
                        </a>
                    @endif
                </form>
            </div>

            <!-- Flash Messages -->
            @foreach (['success' => 'green', 'error' => 'red'] as $key => $color)
                @if(session($key))
                    <div class="rounded-md bg-{{ $color }}-100 px-4 py-3 text-{{ $color }}-700">
                        {{ session($key) }}
                    </div>
                @endif
            @endforeach

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded">
                    <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Borrowed (Active)</h3>
                    <p class="text-3xl font-bold text-blue-700">
                        KES {{ number_format($activeLoans->sum('principal_amount'), 0, '.', ',') }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">{{ $activeLoans->count() }} active loans</p>
                </div>

                <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded">
                    <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Outstanding</h3>
                    <p class="text-3xl font-bold text-red-700">
                        KES {{ number_format($activeLoans->sum('balance'), 0, '.', ',') }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Remaining to repay</p>
                </div>

                <div class="bg-green-50 border-l-4 border-green-500 p-6 rounded">
                    <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Repaid (All Time)</h3>
                    <p class="text-3xl font-bold text-green-700">
                        KES {{ number_format($paidLoans->sum('amount_paid'), 0, '.', ',') }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">All loans combined</p>
                </div>
            </div>

            <!-- Active Loans Table -->
            <div>
                <h2 class="text-2xl font-bold mb-4">Active Loans</h2>
                @if($activeLoans->count() > 0)
                    <div class="bg-white shadow rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200">
                            <!-- Table content unchanged -->
                        </table>
                    </div>
                @else
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
                        <p class="text-gray-500 text-lg">No active loans</p>
                        <p class="text-gray-600 text-sm mt-2">Loans are created when you top up your account using M-Pesa</p>
                    </div>
                @endif
            </div>

            <!-- Paid Loans Table -->
            @if($paidLoans->count() > 0)
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-bold">
                            @if($filter === 'all_paid' || $year)
                                Paid Loans @if($year) ({{ $year }}) @endif
                            @else
                                Recently Paid Loans
                            @endif
                        </h2>
                        @if($filter !== 'all_paid' && !$year)
                            <a href="{{ route('loans.index', ['filter' => 'all_paid']) }}" class="text-blue-600 hover:text-blue-800 text-sm">View All Paid Loans →</a>
                        @endif
                    </div>
                    <div class="bg-white shadow rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200">
                            <!-- Table content unchanged -->
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
