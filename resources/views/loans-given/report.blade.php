<x-app-layout>
    <div class="py-12 print:py-0">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 print:px-0 print:max-w-none">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg print:shadow-none print:rounded-none">
                <div class="p-6 print:p-0">

                    <!-- Toolbar (hidden when printing) -->
                    <div class="flex justify-between items-center mb-6 print:hidden">
                        <h1 class="text-2xl font-semibold text-gray-800">Loan Report</h1>
                        <div class="flex gap-2">
                            <a href="{{ route('loans-given.index') }}"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest bg-white hover:bg-gray-50">
                                Back
                            </a>
                            <button onclick="window.print()"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                Print / Save as PDF
                            </button>
                        </div>
                    </div>

                    <!-- Filters (hidden when printing) -->
                    <form method="GET" action="{{ route('loans-given.report') }}"
                          class="flex flex-wrap items-end gap-3 mb-6 print:hidden">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                            <select name="status"
                                    class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="active" @selected($status === 'active')>Active Loans</option>
                                <option value="paid" @selected($status === 'paid')>Paid Loans</option>
                                <option value="all" @selected($status === 'all')>All Loans</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Referrer</label>
                            <select name="referrer_id"
                                    class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">All Referrers</option>
                                @foreach($referrers as $referrer)
                                    <option value="{{ $referrer->id }}" @selected((string)$referrerId === (string)$referrer->id)>
                                        {{ $referrer->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Apply
                        </button>
                    </form>

                    <!-- Print header (only visible when printing) -->
                    <div class="hidden print:block mb-6">
                        <h1 class="text-xl font-semibold text-gray-900">Loan Report</h1>
                        <p class="text-sm text-gray-500">
                            {{ ucfirst($status) }} loans
                            @if($referrerId && $referrers->firstWhere('id', $referrerId))
                                &middot; Referrer: {{ $referrers->firstWhere('id', $referrerId)->name }}
                            @endif
                            &middot; Generated {{ now()->format('M d, Y H:i') }}
                        </p>
                    </div>

                    @if($groupedLoans->isEmpty())
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p class="text-sm text-blue-700">No loans match this filter.</p>
                        </div>
                    @else
                        @foreach($groupedLoans as $referrerName => $loans)
                            <div class="mb-8 break-inside-avoid">
                                <h2 class="text-base font-semibold text-gray-800 mb-2 border-b border-gray-200 pb-1">
                                    {{ $referrerName }}
                                    <span class="text-xs font-normal text-gray-500">({{ $loans->count() }} loan{{ $loans->count() === 1 ? '' : 's' }})</span>
                                </h2>
                                <table class="min-w-full text-sm">
                                    <thead>
                                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wider">
                                        <th class="py-2 pr-4">Borrower</th>
                                        <th class="py-2 pr-4">Principal</th>
                                        <th class="py-2 pr-4">Outstanding</th>
                                        <th class="py-2 pr-4">Due Date</th>
                                        <th class="py-2 pr-4">Status</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                    @foreach($loans as $loan)
                                        <tr>
                                            <td class="py-2 pr-4 font-medium text-gray-900">{{ $loan->borrower_name }}</td>
                                            <td class="py-2 pr-4">KES {{ number_format($loan->principal_amount, 0) }}</td>
                                            <td class="py-2 pr-4">KES {{ number_format($loan->balance, 0) }}</td>
                                            <td class="py-2 pr-4">
                                                {{ $loan->due_date ? $loan->due_date->format('M d, Y') : 'Not set' }}
                                                @if($loan->due_date && $loan->status === 'active' && $loan->due_date->isPast())
                                                    <span class="ml-1 text-xs text-red-600">(overdue)</span>
                                                @endif
                                            </td>
                                            <td class="py-2 pr-4 capitalize">{{ str_replace('_', ' ', $loan->status) }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                    <tfoot>
                                    <tr class="text-xs text-gray-500 border-t border-gray-200">
                                        <td class="py-2 pr-4 font-medium">Subtotal</td>
                                        <td class="py-2 pr-4 font-medium">KES {{ number_format($loans->sum('principal_amount'), 0) }}</td>
                                        <td class="py-2 pr-4 font-medium">KES {{ number_format($loans->sum('balance'), 0) }}</td>
                                        <td class="py-2 pr-4"></td>
                                        <td class="py-2 pr-4"></td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endforeach

                        <div class="mt-6 pt-4 border-t-2 border-gray-300 flex justify-end gap-8 text-sm">
                            <div>
                                <span class="text-gray-500">Total Principal:</span>
                                <span class="font-semibold text-gray-900">KES {{ number_format($grandTotalPrincipal, 0) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Total Outstanding:</span>
                                <span class="font-semibold text-indigo-600">KES {{ number_format($grandTotalOutstanding, 0) }}</span>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            @page { margin: 1.5cm; }
            body { background: white; }
        }
    </style>
</x-app-layout>
