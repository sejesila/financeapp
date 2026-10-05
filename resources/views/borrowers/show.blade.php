<x-app-layout>
    @php
        $tierStyles = [
            'new'    => 'bg-gray-100 text-gray-700',
            'low'    => 'bg-green-100 text-green-800',
            'medium' => 'bg-amber-100 text-amber-800',
            'high'   => 'bg-red-100 text-red-800',
        ];
        $statusStyles = [
            'active'      => 'bg-green-100 text-green-800',
            'paid'        => 'bg-blue-100 text-blue-800',
            'defaulted'   => 'bg-red-100 text-red-800',
            'written_off' => 'bg-gray-200 text-gray-700',
        ];
        $barColor = ['new' => 'bg-gray-400', 'low' => 'bg-green-500', 'medium' => 'bg-amber-500', 'high' => 'bg-red-500'][$stats['risk_tier']];
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
                    <div class="flex flex-wrap justify-between items-start gap-3 mb-6">
                        <div>
                            <h1 class="text-lg sm:text-2xl font-semibold text-gray-800">{{ $borrower->name }}</h1>
                            <p class="text-xs sm:text-sm text-gray-500 mt-1">
                                {{ $borrower->contact ?: 'No contact saved' }}
                                @if($stats['first_loan'])
                                    · Customer since {{ \Carbon\Carbon::parse($stats['first_loan'])->format('M d, Y') }}
                                @endif
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('borrowers.index') }}"
                               class="inline-flex items-center px-3 sm:px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-[11px] sm:text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                ← Borrowers
                            </a>
                            <a href="{{ route('loans-given.create') }}"
                               class="inline-flex items-center px-3 sm:px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-[11px] sm:text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                New Loan
                            </a>
                        </div>
                    </div>

                    <!-- Risk panel -->
                    <div class="mb-6 border border-gray-200 rounded-lg p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <span class="text-xs sm:text-sm font-medium text-gray-700">Risk assessment</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tierStyles[$stats['risk_tier']] }}">
                                {{ ucfirst($stats['risk_tier']) }} · {{ $stats['risk_score'] }}/100
                            </span>
                        </div>
                        <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-2 {{ $barColor }}" style="width: {{ max(3, $stats['risk_score']) }}%"></div>
                        </div>
                        <ul class="mt-3 text-xs sm:text-sm text-gray-600 list-disc list-inside space-y-0.5">
                            @forelse($stats['risk_reasons'] as $reason)
                                <li>{{ $reason }}</li>
                            @empty
                                <li>{{ $stats['risk_tier'] === 'new' ? 'Not enough repayment history yet.' : 'No risk flags.' }}</li>
                            @endforelse
                        </ul>
                    </div>

                    <!-- Stat cards -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 shadow-sm rounded-lg border border-purple-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Interest Earned</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">KES {{ number_format($stats['interest_earned'], 0) }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-500">Avg rate {{ number_format($stats['avg_interest_rate'], 1) }}% (closed)</p>
                        </div>
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 shadow-sm rounded-lg border border-blue-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Total Borrowed</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">KES {{ number_format($stats['total_borrowed'], 0) }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-500">{{ $stats['loans_count'] }} loan{{ $stats['loans_count'] === 1 ? '' : 's' }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-teal-50 to-teal-100 shadow-sm rounded-lg border border-teal-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Avg Days to Repay</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">
                                {{ $stats['avg_days_to_repay'] !== null ? $stats['avg_days_to_repay'] : '-' }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-500">
                                @if($stats['fastest_days'] !== null)
                                    Fastest {{ $stats['fastest_days'] }}d · slowest {{ $stats['slowest_days'] }}d
                                @else
                                    No closed loans yet
                                @endif
                            </p>
                        </div>
                        <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 shadow-sm rounded-lg border border-indigo-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Outstanding</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">KES {{ number_format($stats['outstanding'], 0) }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-500">{{ $stats['active_count'] }} active</p>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 shadow-sm rounded-lg border border-green-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Repaid Loans</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">{{ $stats['paid_count'] }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-500">of {{ $stats['loans_count'] }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 shadow-sm rounded-lg border border-yellow-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Late Rate</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">{{ $stats['late_rate'] }}%</p>
                            <p class="text-[10px] sm:text-xs text-gray-500">Closed loans over 37 days</p>
                        </div>
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 shadow-sm rounded-lg border border-orange-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Rollovers</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">{{ $stats['rollovers'] }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-500">Across all loans</p>
                        </div>
                        <div class="bg-gradient-to-br from-red-50 to-red-100 shadow-sm rounded-lg border border-red-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Defaulted / Written Off</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">{{ $stats['bad_count'] }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-500">KES {{ number_format($stats['unrecovered'], 0) }} unrecovered</p>
                        </div>
                    </div>

                    <!-- Loan history -->
                    <div class="bg-white rounded-lg border border-gray-200 mb-6">
                        <div class="p-4 border-b border-gray-200">
                            <h2 class="text-base sm:text-lg font-medium text-gray-900">Loan history</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    @foreach(['Disbursed', 'Principal', 'Status', 'Interest', 'Days to Repay', 'Rollovers', 'Referrer', ''] as $h)
                                        <th class="px-3 sm:px-6 py-3 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $h }}</th>
                                    @endforeach
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($loans as $loan)
                                    @php
                                        $days = ($loan->status === 'paid' && $loan->repaid_date)
                                            ? (int) $loan->disbursed_date->copy()->startOfDay()->diffInDays($loan->repaid_date->copy()->startOfDay(), true)
                                            : null;
                                        $soFar = $loan->payments->sum('interest_portion');
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-900">{{ $loan->disbursed_date->format('M d, Y') }}</td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">KES {{ number_format($loan->original_principal, 0) }}</td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusStyles[$loan->status] ?? 'bg-gray-100 text-gray-700' }}">
                                                {{ ucfirst(str_replace('_', ' ', $loan->status)) }}
                                            </span>
                                            @if($loan->isOverdue())
                                                <span class="ml-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Overdue</span>
                                            @endif
                                        </td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                            @if($loan->status === 'paid' && $loan->interest_amount > 0)
                                                KES {{ number_format($loan->interest_amount, 0) }} ({{ number_format($loan->interest_rate, 1) }}%)
                                            @elseif($loan->status === 'active' && $soFar > 0)
                                                KES {{ number_format($soFar, 0) }} <span class="text-[10px] text-gray-400">(so far)</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">{{ $days !== null ? $days . ' days' : '-' }}</td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">{{ $loan->rollover_count ?: '-' }}</td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                            @if($loan->referrer)
                                                <a href="{{ route('referrers.show', $loan->referrer->id) }}" class="text-indigo-600 hover:text-indigo-900">{{ $loan->referrer->name }}</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm">
                                            <a href="{{ route('loans-given.show', $loan->id) }}" class="text-indigo-600 hover:text-indigo-900">
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
                    </div>

                    <!-- Merge duplicate -->
                    @if($others->isNotEmpty())
                        <div class="border border-gray-200 rounded-lg" x-data="{ open: false }">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center justify-between px-4 py-3 text-left">
                                <span class="text-xs sm:text-sm font-medium text-gray-700">Same person under another name? Merge a duplicate</span>
                                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <form x-show="open" x-cloak method="POST" action="{{ route('borrowers.merge', $borrower->id) }}"
                                  onsubmit="return confirm('Move all of that borrower\'s loans onto {{ addslashes($borrower->name) }} and delete the duplicate?')"
                                  class="px-4 pb-4 flex flex-wrap items-center gap-2">
                                @csrf
                                <select name="source_id" required
                                        class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">Select duplicate…</option>
                                    @foreach($others as $o)
                                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit"
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    Merge into {{ $borrower->name }}
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
