@extends('layout')

@section('content')
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Financial Reports</h1>
        <p class="text-gray-600">Analyze your spending patterns and trends</p>
    </div>

    <!-- Filter Controls -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Time Period</label>
                <select name="filter" onchange="toggleCustomDates(this.value)" class="border rounded p-2">
                    <option value="this_month" {{ $filter == 'this_month' ? 'selected' : '' }}>This Month</option>
                    <option value="last_month" {{ $filter == 'last_month' ? 'selected' : '' }}>Last Month</option>
                    <option value="this_year" {{ $filter == 'this_year' ? 'selected' : '' }}>This Year</option>
                    <option value="last_year" {{ $filter == 'last_year' ? 'selected' : '' }}>Last Year</option>
                    <option value="custom" {{ $filter == 'custom' ? 'selected' : '' }}>Custom Range</option>
                </select>
            </div>

            <div id="customDates" style="display: {{ $filter == 'custom' ? 'flex' : 'none' }};" class="flex gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Start Date</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}" class="border rounded p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">End Date</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}" class="border rounded p-2">
                </div>
            </div>

            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700">
                Apply Filter
            </button>
        </form>
    </div>

    <script>
        function toggleCustomDates(value) {
            document.getElementById('customDates').style.display = value === 'custom' ? 'flex' : 'none';
        }
    </script>

    <!-- Cash Flow Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-green-500">
            <p class="text-sm text-gray-600 font-semibold mb-1">Total Income</p>
            <p class="text-3xl font-bold text-green-600">{{ number_format($totalIncome, 0, '.', ',') }}</p>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-red-500">
            <p class="text-sm text-gray-600 font-semibold mb-1">Total Expenses</p>
            <p class="text-3xl font-bold text-red-600">{{ number_format($totalExpenses, 0, '.', ',') }}</p>
            @if($previousExpenses > 0)
                <p class="text-xs mt-1 {{ $expenseChange > 0 ? 'text-red-500' : 'text-green-500' }}">
                    {{ $expenseChange > 0 ? '↑' : '↓' }} {{ abs($expenseChange) }}% vs previous period
                </p>
            @endif
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 {{ $netCashFlow >= 0 ? 'border-blue-500' : 'border-orange-500' }}">
            <p class="text-sm text-gray-600 font-semibold mb-1">Net Cash Flow</p>
            <p class="text-3xl font-bold {{ $netCashFlow >= 0 ? 'text-blue-600' : 'text-orange-600' }}">
                {{ number_format($netCashFlow, 0, '.', ',') }}
            </p>
            <p class="text-xs text-gray-500 mt-1">
                {{ $netCashFlow >= 0 ? 'Surplus' : 'Deficit' }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Spending by Category (Pie Chart) -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold mb-4">Spending by Category</h3>
            <div style="height: 300px;">
                <canvas id="categoryPieChart"></canvas>
            </div>

            <div class="mt-4 space-y-2">
                @foreach($topCategories as $category)
                    <div class="flex justify-between items-center">
                        <span class="text-sm">{{ $category->name }}</span>
                        <span class="text-sm font-semibold">{{ number_format($category->total, 0, '.', ',') }} ({{ $totalExpenses > 0 ? round(($category->total / $totalExpenses) * 100, 1) : 0 }}%)</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Income vs Expenses (Bar Chart) -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold mb-4">Income vs Expenses</h3>
            <div style="height: 300px;">
                <canvas id="incomeExpenseChart"></canvas>
            </div>
        </div>
    </div>



    <!-- Chart.js Script -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Spending by Category Pie Chart
        const categoryCtx = document.getElementById('categoryPieChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: {!! json_encode($expensesByCategory->pluck('name')) !!},
                datasets: [{
                    data: {!! json_encode($expensesByCategory->pluck('total')) !!},
                    backgroundColor: [
                        '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
                        '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.5,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        // Income vs Expenses Bar Chart
        const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
        const incomeExpenseChart = new Chart(incomeExpenseCtx, {
            type: 'bar',
            data: {
                labels: ['Income', 'Expenses', 'Net'],
                datasets: [{
                    label: 'Amount',
                    data: [
                        {{ $totalIncome }},
                        {{ $totalExpenses }},
                        {{ abs($netCashFlow) }}
                    ],
                    backgroundColor: ['#10B981', '#EF4444', '{{ $netCashFlow >= 0 ? "#3B82F6" : "#F59E0B" }}']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });


    </script>
@endsection
