<x-app-layout>
    @php
        $tierStyles = [
            'new'    => 'bg-gray-100 text-gray-700',
            'low'    => 'bg-green-100 text-green-800',
            'medium' => 'bg-amber-100 text-amber-800',
            'high'   => 'bg-red-100 text-red-800',
        ];
        $sorts = [
            'interest_earned' => 'Interest earned',
            'total_borrowed'  => 'Total borrowed',
            'outstanding'     => 'Outstanding',
            'risk_score'      => 'Risk',
            'name'            => 'Name',
        ];
        $repeat   = $rows->where('loans_count', '>', 1)->count();
        $highRisk = $rows->where('risk_tier', 'high')->count();
    @endphp

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    @if(session('success'))
                        <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4">
                            <p class="text-sm text-green-700">{{ session('success') }}</p>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4">
                            <p class="text-sm text-red-700">{{ session('error') }}</p>
                        </div>
                    @endif

                    <!-- Header -->
                    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
                        <h1 class="text-lg sm:text-2xl font-semibold text-gray-800">Borrowers</h1>
                        <a href="{{ route('loans-given.index') }}"
                           class="inline-flex items-center px-3 sm:px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-[11px] sm:text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            ← Loans
                        </a>
                    </div>

                    <!-- Summary cards -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
                        <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 shadow-sm rounded-lg border border-indigo-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Borrowers</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">{{ $rows->count() }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-500">{{ $repeat }} returning</p>
                        </div>
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 shadow-sm rounded-lg border border-purple-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Interest Earned</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">KES {{ number_format($rows->sum('interest_earned'), 0) }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-500">All time, all borrowers</p>
                        </div>
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 shadow-sm rounded-lg border border-blue-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Outstanding</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">KES {{ number_format($rows->sum('outstanding'), 0) }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-500">Active loans</p>
                        </div>
                        <div class="bg-gradient-to-br from-red-50 to-red-100 shadow-sm rounded-lg border border-red-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">High Risk</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">{{ $highRisk }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-500">KES {{ number_format($rows->sum('unrecovered'), 0) }} unrecovered</p>
                        </div>
                    </div>

                    <!-- Sort -->
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider mr-1">Sort by</span>
                        @foreach($sorts as $key => $label)
                            <a href="{{ route('borrowers.index', ['sort' => $key]) }}"
                               class="px-3 py-1.5 rounded-full text-xs font-medium border {{ $sort === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>

                    <!-- Table -->
                    <div class="bg-white rounded-lg border border-gray-200">
                        @if($rows->isEmpty())
                            <div class="p-6">
                                <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                                    <p class="text-sm text-blue-700">No borrowers yet.</p>
                                </div>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        @foreach(['Borrower', 'Loans', 'Total Borrowed', 'Interest Earned', 'Avg Days to Repay', 'Outstanding', 'Risk', 'Last Loan', ''] as $h)
                                            <th class="px-3 sm:px-6 py-3 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $h }}</th>
                                        @endforeach
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($rows as $r)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium text-gray-900">
                                                <a href="{{ route('borrowers.show', $r['borrower']->id) }}" class="hover:text-indigo-600">
                                                    {{ $r['borrower']->name }}
                                                </a>
                                            </td>
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                                {{ $r['loans_count'] }}
                                                <span class="text-[10px] text-gray-400">
                                                    ({{ $r['active_count'] }} active{{ $r['bad_count'] ? ', ' . $r['bad_count'] . ' bad' : '' }})
                                                </span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                                KES {{ number_format($r['total_borrowed'], 0) }}</td>
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium text-purple-700">
                                                KES {{ number_format($r['interest_earned'], 0) }}</td>
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                                {{ $r['avg_days_to_repay'] !== null ? $r['avg_days_to_repay'] . ' days' : '-' }}</td>
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                                {{ $r['outstanding'] > 0 ? 'KES ' . number_format($r['outstanding'], 0) : '-' }}
                                                @if($r['overdue_now'])
                                                    <span class="ml-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Overdue</span>
                                                @endif
                                            </td>
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm">
                                                <span title="{{ implode('; ', $r['risk_reasons']) }}"
                                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tierStyles[$r['risk_tier']] }}">
                                                    {{ ucfirst($r['risk_tier']) }} · {{ $r['risk_score'] }}
                                                </span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                                {{ $r['last_loan'] ? \Carbon\Carbon::parse($r['last_loan'])->format('M d, Y') : '-' }}</td>
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm">
                                                <a href="{{ route('borrowers.show', $r['borrower']->id) }}" class="text-indigo-600 hover:text-indigo-900">
                                                    <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
