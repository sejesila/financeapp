<x-app-layout>
    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
                        <h1 class="text-lg sm:text-2xl font-semibold text-gray-800">Borrowers by Month</h1>
                        <div class="flex gap-2">
                            @foreach([6, 12, 24] as $n)
                                <a href="{{ route('borrowers.monthly', ['months' => $n]) }}"
                                   class="px-3 py-1.5 rounded-full text-xs font-medium border {{ $months === $n ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                                    {{ $n }}m
                                </a>
                            @endforeach
                            <a href="{{ route('borrowers.index') }}"
                               class="px-3 py-1.5 rounded-md border border-gray-300 text-xs font-semibold uppercase text-gray-700 hover:bg-gray-50">← Borrowers</a>
                        </div>
                    </div>

                    @php
                        $cards = [
                            ['New (period)',                $totals['new'],                                                       'from-indigo-50 to-indigo-100 border-indigo-200'],
                            ['Returning (period)',          $totals['returning'],                                                 'from-purple-50 to-purple-100 border-purple-200'],
                            ['Avg new / month',             $totals['avg_new_per_month'],                                         'from-blue-50 to-blue-100 border-blue-200'],
                            ['Repeat borrowers (all time)', $totals['repeat_borrowers'] . ' of ' . $totals['total_borrowers'],    'from-green-50 to-green-100 border-green-200'],
                        ];
                    @endphp
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
                        @foreach($cards as [$label, $value, $classes])
                            <div class="bg-gradient-to-br {{ $classes }} rounded-lg border p-3 sm:p-4">
                                <p class="text-xs sm:text-sm font-medium text-gray-500">{{ $label }}</p>
                                <p class="text-base sm:text-lg font-semibold text-gray-900">{{ $value }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="h-56 sm:h-72 mb-6"><canvas id="borrowersChart"></canvas></div>

                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                @foreach(['Month', 'New', 'Returning', 'Active', 'Loans', 'Disbursed (New)', 'Disbursed (Returning)', '% Returning', 'Retention*'] as $h)
                                    <th class="px-3 sm:px-6 py-3 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $h }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            @foreach(array_reverse($rows) as $r)
                                <tr class="hover:bg-gray-50 text-xs sm:text-sm text-gray-500">
                                    <td class="px-3 sm:px-6 py-3 font-medium text-gray-900 whitespace-nowrap">{{ $r['label'] }}</td>
                                    <td class="px-3 sm:px-6 py-3 font-medium text-indigo-700">{{ $r['new'] }}</td>
                                    <td class="px-3 sm:px-6 py-3 font-medium text-purple-700">{{ $r['returning'] }}</td>
                                    <td class="px-3 sm:px-6 py-3">{{ $r['active'] }}</td>
                                    <td class="px-3 sm:px-6 py-3">{{ $r['loans'] }}</td>
                                    <td class="px-3 sm:px-6 py-3 whitespace-nowrap">KES {{ number_format($r['disbursed_new'], 0) }}</td>
                                    <td class="px-3 sm:px-6 py-3 whitespace-nowrap">KES {{ number_format($r['disbursed_returning'], 0) }}</td>
                                    <td class="px-3 sm:px-6 py-3">{{ $r['returning_share'] !== null ? $r['returning_share'] . '%' : '-' }}</td>
                                    <td class="px-3 sm:px-6 py-3 whitespace-nowrap">
                                        {{ $r['retention'] !== null ? $r['retention'] . '% (' . $r['retained'] . ')' : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-400">
                        *Retention = share of the previous month's borrowers who took another loan this month.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rows = @json($rows);
            new Chart(document.getElementById('borrowersChart'), {
                type: 'bar',
                data: {
                    labels: rows.map(r => r.label),
                    datasets: [
                        {label: 'New', data: rows.map(r => r.new), backgroundColor: 'rgba(79,70,229,0.85)', borderRadius: 4},
                        {label: 'Returning', data: rows.map(r => r.returning), backgroundColor: 'rgba(168,85,247,0.85)', borderRadius: 4},
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: {mode: 'index', intersect: false},
                    scales: {x: {stacked: true}, y: {stacked: true, beginAtZero: true, ticks: {precision: 0}}},
                },
            });
        });
    </script>
</x-app-layout>
