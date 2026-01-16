<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200">
                Reports
            </h2>
            <p class="text-xs md:text-sm text-gray-500">
                Analyze your spending patterns and trends
            </p>
        </div>
    </x-slot>

    <div class="mx-auto mt-4 md:mt-8 max-w-7xl space-y-6 md:space-y-8 px-4 md:px-0">

        {{-- Filters --}}
        <div class="rounded-lg border bg-white p-4 md:p-5 shadow-sm">
            <form method="GET"
                  action="{{ route('reports.index') }}"
                  class="flex flex-col md:flex-row md:flex-wrap items-start md:items-end gap-4">

                <div class="w-full md:w-auto">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Time Period
                    </label>
                    <select name="filter"
                            onchange="toggleCustomDates(this.value)"
                            class="w-full md:w-auto rounded-md border border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2">
                        @foreach([
                            'this_month' => 'This Month',
                            'last_month' => 'Last Month',
                            'this_year'  => 'This Year',
                            'last_year'  => 'Last Year',
                            'custom'     => 'Custom Range',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($filter === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div id="customDates"
                     class="w-full flex flex-col md:flex-row gap-4 {{ $filter === 'custom' ? '' : 'hidden' }}">
                    <div class="flex-1 md:flex-initial">
                        <label class="block text-sm font-medium mb-1">Start</label>
                        <input type="date"
                               name="start_date"
                               value="{{ request('start_date') }}"
                               class="w-full rounded-md border border-gray-300 text-sm px-3 py-2">
                    </div>

                    <div class="flex-1 md:flex-initial">
                        <label class="block text-sm font-medium mb-1">End</label>
                        <input type="date"
                               name="end_date"
                               value="{{ request('end_date') }}"
                               class="w-full rounded-md border border-gray-300 text-sm px-3 py-2">
                    </div>
                </div>

                <button type="submit"
                        class="w-full md:w-auto rounded-md bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition">
                    Apply
                </button>
            </form>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
            <x-report-card
                title="Total Income"
                :value="$totalIncome"
                color="green" />

            <x-report-card
                title="Total Expenses"
                :value="$totalExpenses"
                color="red">
                @if($previousExpenses > 0)
                    <p class="text-xs mt-1 {{ $expenseChange > 0 ? 'text-red-500' : 'text-green-500' }}">
                        {{ $expenseChange > 0 ? '↑' : '↓' }} {{ abs($expenseChange) }}% vs previous
                    </p>
                @endif
            </x-report-card>

            <x-report-card
                title="Net Cash Flow"
                :value="$netCashFlow"
                :color="$netCashFlow >= 0 ? 'blue' : 'orange'"
                subtitle="{{ $netCashFlow >= 0 ? 'Surplus' : 'Deficit' }}" />
        </div>

        {{-- Charts --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">

            {{-- Spending by Category --}}
            <div class="rounded-lg border bg-white p-4 md:p-6 shadow-sm">
                <h3 class="text-base md:text-lg font-semibold mb-4">
                    Spending by Category
                </h3>

                <div class="h-64 md:h-72">
                    <canvas id="categoryPieChart"></canvas>
                </div>

                <div class="mt-4 space-y-2 text-xs md:text-sm">
                    @forelse($topCategories as $category)
                        <div class="flex justify-between items-center">
                            <span class="truncate">{{ $category->name }}</span>
                            <span class="font-medium text-right ml-2">
                                {{ number_format($category->total) }}
                                ({{ $totalExpenses > 0 ? round(($category->total / $totalExpenses) * 100, 1) : 0 }}%)
                            </span>
                        </div>
                    @empty
                        <p class="text-gray-500 text-center">
                            No expense data available
                        </p>
                    @endforelse
                </div>
            </div>

            {{-- Income vs Expenses --}}
            <div class="rounded-lg border bg-white p-4 md:p-6 shadow-sm">
                <h3 class="text-base md:text-lg font-semibold mb-4">
                    Income vs Expenses
                </h3>

                <div class="h-64 md:h-72">
                    <canvas id="incomeExpenseChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Mobile Money Transaction Type Stats --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
            {{-- M-Pesa Stats --}}
            @if($transactionTypeStats['mpesa']['period']->isNotEmpty())
                <div class="rounded-lg border bg-white p-4 md:p-6 shadow-sm">
                    <h3 class="text-base md:text-lg font-semibold mb-4">
                        📱 M-Pesa Transaction Types
                    </h3>

                    <div class="space-y-2 md:space-y-3">
                        @foreach($transactionTypeStats['mpesa']['period'] as $stat)
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded gap-2">
                                <div class="min-w-0">
                                    <p class="font-medium text-xs md:text-sm capitalize truncate">
                                        {{ str_replace('_', ' ', $stat->type) }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $stat->count }} transaction{{ $stat->count !== 1 ? 's' : '' }}
                                    </p>
                                </div>
                                <div class="sm:text-right">
                                    <p class="font-semibold text-xs md:text-sm">
                                        KSh {{ number_format($stat->total, 0) }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ round(($stat->count / $transactionTypeStats['mpesa']['period']->sum('count')) * 100, 1) }}%
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($transactionTypeStats['mpesa']['frequency']->isNotEmpty())
                        <div class="mt-3 md:mt-4 pt-3 md:pt-4 border-t">
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2">
                                ALL-TIME PREFERENCE
                            </p>
                            <div class="space-y-1 md:space-y-2">
                                @foreach($transactionTypeStats['mpesa']['frequency'] as $freq)
                                    <div class="flex justify-between items-center text-xs gap-2">
                                        <span class="capitalize truncate">{{ str_replace('_', ' ', $freq->transaction_type) }}</span>
                                        <span class="font-medium whitespace-nowrap">{{ $freq->usage_count }} uses</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Airtel Money Stats --}}
            @if($transactionTypeStats['airtel_money']['period']->isNotEmpty())
                <div class="rounded-lg border bg-white p-4 md:p-6 shadow-sm">
                    <h3 class="text-base md:text-lg font-semibold mb-4">
                        📲 Airtel Money Transaction Types
                    </h3>

                    <div class="space-y-2 md:space-y-3">
                        @foreach($transactionTypeStats['airtel_money']['period'] as $stat)
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded gap-2">
                                <div class="min-w-0">
                                    <p class="font-medium text-xs md:text-sm capitalize truncate">
                                        {{ str_replace('_', ' ', $stat->type) }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $stat->count }} transaction{{ $stat->count !== 1 ? 's' : '' }}
                                    </p>
                                </div>
                                <div class="sm:text-right">
                                    <p class="font-semibold text-xs md:text-sm">
                                        KSh {{ number_format($stat->total, 0) }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ round(($stat->count / $transactionTypeStats['airtel_money']['period']->sum('count')) * 100, 1) }}%
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($transactionTypeStats['airtel_money']['frequency']->isNotEmpty())
                        <div class="mt-3 md:mt-4 pt-3 md:pt-4 border-t">
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2">
                                ALL-TIME PREFERENCE
                            </p>
                            <div class="space-y-1 md:space-y-2">
                                @foreach($transactionTypeStats['airtel_money']['frequency'] as $freq)
                                    <div class="flex justify-between items-center text-xs gap-2">
                                        <span class="capitalize truncate">{{ str_replace('_', ' ', $freq->transaction_type) }}</span>
                                        <span class="font-medium whitespace-nowrap">{{ $freq->usage_count }} uses</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <script>
        function toggleCustomDates(value) {
            document.getElementById('customDates')
                .classList.toggle('hidden', value !== 'custom');
        }

        new Chart(document.getElementById('categoryPieChart'), {
            type: 'pie',
            data: {
                labels: @json($expensesByCategory->pluck('name')),
                datasets: [{
                    data: @json($expensesByCategory->pluck('total')),
                    backgroundColor: [
                        '#3B82F6','#EF4444','#10B981','#F59E0B','#8B5CF6',
                        '#EC4899','#14B8A6','#F97316','#6366F1','#84CC16'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: window.innerWidth < 768 ? 'bottom' : 'right',
                        labels: { font: { size: window.innerWidth < 768 ? 10 : 12 } }
                    }
                }
            }
        });

        new Chart(document.getElementById('incomeExpenseChart'), {
            type: 'bar',
            data: {
                labels: ['Income', 'Expenses', 'Net'],
                datasets: [{
                    data: [
                        {{ $totalIncome }},
                        {{ $totalExpenses }},
                        {{ abs($netCashFlow) }}
                    ],
                    backgroundColor: [
                        '#10B981',
                        '#EF4444',
                        '{{ $netCashFlow >= 0 ? "#3B82F6" : "#F59E0B" }}'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
    <x-floating-action-button :quickAccount="$accounts->first()" />
</x-app-layout>
