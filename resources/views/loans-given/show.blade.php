<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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

            <!-- Loan Details Card -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">Loan Details</h2>
                        <div class="flex items-center space-x-2">
                            @if($loanGiven->status === 'active')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">Active</span>
                                @if($isOverdue)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">Overdue</span>
                                @endif
                            @elseif($loanGiven->status === 'paid')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">Paid</span>
                            @elseif($loanGiven->status === 'defaulted')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">Defaulted</span>
                            @elseif($loanGiven->status === 'written_off')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">Written Off</span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Borrower Info -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-3">Borrower Information</h3>
                            <dl class="space-y-2">
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <dt class="text-sm font-medium text-gray-500">Name</dt>
                                    <dd class="text-sm text-gray-900">{{ $loanGiven->borrower_name }}</dd>
                                </div>
                                @if($loanGiven->borrower_contact)
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <dt class="text-sm font-medium text-gray-500">Contact</dt>
                                        <dd class="text-sm text-gray-900">{{ $loanGiven->borrower_contact }}</dd>
                                    </div>
                                @endif
                                @if($loanGiven->account)
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <dt class="text-sm font-medium text-gray-500">Account</dt>
                                        <dd class="text-sm text-gray-900">{{ $loanGiven->account->name }}</dd>
                                    </div>
                                @endif
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <dt class="text-sm font-medium text-gray-500">Referred By</dt>
                                    <dd class="text-sm text-gray-900">
                                        @if($loanGiven->referrer)
                                            {{ $loanGiven->referrer->name }}
                                            @if($loanGiven->referrer_share_percentage !== null)
                                                <span class="text-xs text-gray-500">({{ number_format($loanGiven->referrer_share_percentage, 1) }}% of interest)</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">Direct — no referrer</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Loan Details -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-3">Loan Details</h3>
                            <dl class="space-y-2">
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <dt class="text-sm font-medium text-gray-500">Principal</dt>
                                    <dd class="text-sm text-gray-900">KES {{ number_format($loanGiven->principal_amount, 0) }}</dd>
                                </div>
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <dt class="text-sm font-medium text-gray-500">Interest</dt>
                                    <dd class="text-sm text-gray-900">
                                        @if($loanGiven->status === 'paid')
                                            @if($loanGiven->interest_amount > 0)
                                                KES {{ number_format($loanGiven->interest_amount, 0) }}
                                                ({{ number_format($loanGiven->interest_rate, 1) }}%)
                                            @else
                                                <span class="text-gray-500">None — repaid at principal only</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400 text-sm">Calculated when loan is closed</span>
                                        @endif
                                    </dd>
                                </div>
                                @if($loanGiven->status === 'paid' && $loanGiven->referrer)
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <dt class="text-sm font-medium text-gray-500">Referrer's Cut</dt>
                                        <dd class="text-sm text-gray-900">
                                            @if($referrerPayout !== null)
                                                KES {{ number_format($referrerPayout, 0) }}
                                                <span class="text-xs text-gray-500">({{ number_format($loanGiven->referrer_share_percentage, 1) }}% of interest)</span>
                                            @else
                                                <span class="text-gray-500">Nothing due — no interest was earned</span>
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <dt class="text-sm font-medium text-gray-500">Total Received</dt>
                                    <dd class="text-sm text-gray-900 font-medium">KES {{ number_format($loanGiven->amount_paid, 0) }}</dd>
                                </div>
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <dt class="text-sm font-medium text-gray-500">Outstanding Principal</dt>
                                    <dd class="text-sm font-bold text-indigo-600">KES {{ number_format($loanGiven->balance, 0) }}</dd>
                                </div>
                                @if($loanGiven->status === 'active' && $loanGiven->surplus_received > 0)
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <dt class="text-sm font-medium text-gray-500">Received Above Principal So Far</dt>
                                        <dd class="text-sm text-purple-600 font-medium">KES {{ number_format($loanGiven->surplus_received, 0) }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-6 border-t border-gray-200">
                        <div>
                            <span class="text-sm font-medium text-gray-500">Disbursed:</span>
                            <span class="ml-2 text-sm text-gray-900">{{ $loanGiven->disbursed_date->format('M d, Y') }}</span>
                            @if($daysElapsed > 0)
                                <span class="ml-1 text-xs text-gray-500">({{ $daysElapsed }} day{{ $daysElapsed > 1 ? 's' : '' }} ago)</span>
                            @else
                                <span class="ml-1 text-xs text-green-500">(Today)</span>
                            @endif
                        </div>
                        @if($loanGiven->due_date)
                            <div>
                                <span class="text-sm font-medium text-gray-500">Due:</span>
                                <span class="ml-2 text-sm text-gray-900">{{ $loanGiven->due_date->format('M d, Y') }}</span>
                                @if($daysRemaining !== null && $loanGiven->status === 'active')
                                    @if($daysRemaining > 0)
                                        <span class="ml-1 text-xs text-blue-500">({{ $daysRemaining }} day{{ $daysRemaining > 1 ? 's' : '' }} remaining)</span>
                                    @elseif($daysRemaining == 0)
                                        <span class="ml-1 text-xs text-yellow-500">(Due today)</span>
                                    @else
                                        <span class="ml-1 text-xs text-red-500">({{ abs($daysRemaining) }} day{{ abs($daysRemaining) > 1 ? 's' : '' }} overdue)</span>
                                    @endif
                                @endif
                            </div>
                        @endif
                        @if($loanGiven->repaid_date)
                            <div>
                                <span class="text-sm font-medium text-gray-500">Repaid:</span>
                                <span class="ml-2 text-sm text-gray-900">{{ $loanGiven->repaid_date->format('M d, Y') }}</span>
                            </div>
                        @endif
                    </div>

                    @if($loanGiven->notes)
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Notes</h4>
                            <p class="text-sm text-gray-700">{{ $loanGiven->notes }}</p>
                        </div>
                    @endif

                    <!-- Actions -->
                    <div class="mt-6 pt-6 border-t border-gray-200 flex flex-wrap gap-2">
                        @if($loanGiven->status === 'active')
                            <a href="{{ route('loans-given.payment', $loanGiven->id) }}"
                               class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v1m0 1v1m0 1v1m0 1v1"></path>
                                </svg>
                                Record Payment
                            </a>

                            <button type="button" onclick="document.getElementById('closeLoanModal').showModal()"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Close as Fully Repaid
                            </button>

                            <button type="button" onclick="document.getElementById('changeStatusModal').showModal()"
                                    class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 focus:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Change Status
                            </button>
                        @endif

                        @if($loanGiven->status === 'active' && $loanGiven->amount_paid == 0)
                            <form method="POST" action="{{ route('loans-given.destroy', $loanGiven->id) }}"
                                  onsubmit="return confirm('Are you sure you want to delete this loan? This action cannot be undone.')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                    Delete
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('loans-given.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back
                        </a>
                    </div>
                </div>
            </div>

            <!-- Payment History Card -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Payment History</h3>

                    @if($loanGiven->payments->isEmpty())
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700">No payments recorded yet.</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($loanGiven->payments as $payment)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $payment->payment_date->format('M d, Y') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">KES {{ number_format($payment->amount, 0) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $payment->account->name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ $payment->notes ?? '-' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-900 uppercase tracking-wider">KES {{ number_format($loanGiven->payments->sum('amount'), 0) }}</th>
                                    <th colspan="2"></th>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Close as Fully Repaid Modal -->
    @if($loanGiven->status === 'active')
        <dialog id="closeLoanModal" class="rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Close Loan as Fully Repaid</h3>
                    <button type="button" onclick="document.getElementById('closeLoanModal').close()" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="text-sm text-gray-600 space-y-2 mb-4">
                    <p>Principal: <span class="font-medium text-gray-900">KES {{ number_format($loanGiven->principal_amount, 0) }}</span></p>
                    <p>Total received so far: <span class="font-medium text-gray-900">KES {{ number_format($loanGiven->amount_paid, 0) }}</span></p>
                    <p class="pt-2 border-t border-gray-100">
                        Closing now will mark this loan as paid and calculate interest as
                        <span class="font-medium text-gray-900">KES {{ number_format($loanGiven->surplus_received, 0) }}</span>
                        ({{ $loanGiven->principal_amount > 0 ? number_format(($loanGiven->surplus_received / $loanGiven->principal_amount) * 100, 1) : 0 }}%).
                    </p>
                    @if($loanGiven->referrer)
                        <p>
                            Referrer <span class="font-medium text-gray-900">{{ $loanGiven->referrer->name }}</span>
                            will be owed
                            <span class="font-medium text-gray-900">
                                KES {{ number_format($loanGiven->surplus_received * (($loanGiven->referrer_share_percentage ?? 0) / 100), 0) }}
                            </span>
                            ({{ number_format($loanGiven->referrer_share_percentage ?? 0, 1) }}% of that interest).
                        </p>
                    @endif
                    @if($loanGiven->remaining_principal > 0)
                        <p class="text-amber-600">
                            ⚠️ Note: KES {{ number_format($loanGiven->remaining_principal, 0) }} of principal hasn't
                            been received yet — closing now treats the loan as settled anyway.
                        </p>
                    @endif
                </div>

                <form method="POST" action="{{ route('loans-given.close', $loanGiven->id) }}">
                    @csrf

                    @if($closingLandsInFloat)
                        <div class="mb-4 border border-amber-200 bg-amber-50 rounded-lg p-4">
                            <label for="interest_account_id_modal" class="block text-sm font-medium text-gray-700">
                                Deposit Interest Into <span class="text-red-600">*</span>
                            </label>
                            <select id="interest_account_id_modal" name="interest_account_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
                                <option value="">Select Account</option>
                                @foreach($interestDestinationAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }} (KES {{ number_format($account->current_balance, 0) }})</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-600">
                                The last payment landed in a referrer float account — the interest shouldn't stay there.
                            </p>
                        </div>
                    @endif

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('closeLoanModal').close()"
                                class="px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Confirm & Close Loan
                        </button>
                    </div>
                </form>
            </div>
        </dialog>
    @endif

    <!-- Change Status Modal -->
    @if($loanGiven->status === 'active')
        <dialog id="changeStatusModal" class="rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Change Loan Status</h3>
                    <button type="button" onclick="document.getElementById('changeStatusModal').close()" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('loans-given.status', $loanGiven->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select new status</label>
                        <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
                            <option value="">Choose...</option>
                            <option value="defaulted">Defaulted</option>
                            <option value="written_off">Written Off</option>
                        </select>
                        <p class="mt-2 text-sm text-gray-500">
                            <span class="font-medium">Defaulted:</span> Borrower has failed to repay<br>
                            <span class="font-medium">Written Off:</span> Loan considered uncollectible
                        </p>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('changeStatusModal').close()"
                                class="px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 focus:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Update Status
                        </button>
                    </div>
                </form>
            </div>
        </dialog>
    @endif
</x-app-layout>
