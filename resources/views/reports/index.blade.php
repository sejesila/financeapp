<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Reports
            </h2>
            <p class="text-sm text-gray-500">
                Analyze your spending patterns and trends
            </p>
        </div>
    </x-slot>

    <div class="mx-auto mt-8 max-w-7xl space-y-8">

        {{-- Filters --}}
        <div class="rounded-lg border bg-white p-5 shadow-sm">
            <form method="GET"
                  action="{{ route('reports.index') }}"
                  class="flex flex-wrap items-end gap-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Time Period
                    </label>
                    <select name="filter"
                            onchange="toggleCustomDates(this.value)"
                            class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                     class="flex gap-4 {{ $filter === 'custom' ? '' : 'hidden' }}">
                    <div>
                        <label class="block text-sm font-medium mb-1">Start</label>
                        <input type="date"
                               name="start_date"
                               value="{{ request('start_date') }}"
                               class="rounded-md border-gray-300 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">End</label>
                        <input type="date"
                               name="end_date"
                               value="{{ request('end_date') }}"
                               class="rounded-md border-gray-300 text-sm">
                    </div>
                </div>

                <button type="submit"
                        class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition">
                    Apply
                </button>
            </form>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Spending by Category --}}
            <div class="rounded-lg border bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold mb-4">
                    Spending by Category
                </h3>

                <div class="h-72">
                    <canvas id="categoryPieChart"></canvas>
                </div>

                <div class="mt-4 space-y-2 text-sm">
                    @forelse($topCategories as $category)
                        <div class="flex justify-between">
                            <span>{{ $category->name }}</span>
                            <span class="font-medium">
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
            <div class="rounded-lg border bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold mb-4">
                    Income vs Expenses
                </h3>

                <div class="h-72">
                    <canvas id="incomeExpenseChart"></canvas>
                </div>
            </div>
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
                plugins: { legend: { position: 'right' } }
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
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
    <x-floating-action-button :quickAccount="$accounts->first()" />
</x-app-layout>
