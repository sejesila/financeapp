@extends('layout')

@section('content')
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold">{{ $loan->source }}</h1>
                <p class="text-gray-600 text-sm mt-1">Loan ID: #{{ $loan->id }} • Created {{ $loan->created_at->format('M d, Y') }}</p>
            </div>
            <div class="space-x-2">
                @if($loan->status === 'active')
                    <a href="{{ route('loans.payment', $loan) }}" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 font-semibold">
                        Make Payment
                    </a>
                @endif
                <a href="{{ route('loans.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                    ← Back to Loans
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-100 text-green-700 p-4 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        <!-- Main Summary Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Loan Details -->
            <div class="lg:col-span-2 bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-bold mb-6">Loan Information</h2>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <span class="text-sm text-gray-600 font-medium">Loan Source</span>
                        <p class="text-lg font-semibold text-gray-900 mt-1">{{ $loan->source }}</p>
                    </div>

                    <div>
                        <span class="text-sm text-gray-600 font-medium">Account</span>
                        <p class="text-lg font-semibold text-gray-900 mt-1">{{ $loan->account->name }}</p>
                    </div>

                    <div>
                        <span class="text-sm text-gray-600 font-medium">Disbursement Date</span>
                        <p class="text-lg font-semibold text-gray-900 mt-1">{{ $loan->disbursed_date->format('M d, Y') }}</p>
                    </div>

                    <div>
                        <span class="text-sm text-gray-600 font-medium">Status</span>
                        <div class="mt-1">
                            <span class="px-3 py-1 rounded-full text-sm font-semibold
                                {{ $loan->status === 'active' ? 'bg-yellow-100 text-yellow-800' :
                                   ($loan->status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') }}">
                                {{ ucfirst($loan->status) }}
                            </span>
                        </div>
                    </div>

                    @if($loan->due_date)
                        <div>
                            <span class="text-sm text-gray-600 font-medium">Due Date</span>
                            <p class="text-lg font-semibold text-gray-900 mt-1">{{ $loan->due_date->format('M d, Y') }}</p>
                        </div>
                        @if($loan->status === 'active')
                            <div>
                                <span class="text-sm text-gray-600 font-medium">Days Remaining</span>
                                @php
                                    $now = now();
                                    $dueDate = $loan->due_date;
                                    $isOverdue = $now->isAfter($dueDate);
                                    $daysDiff = round(abs($now->diffInDays($dueDate)));
                                @endphp
                                <p class="text-lg font-semibold {{ $isOverdue ? 'text-red-600' : ($daysDiff < 7 ? 'text-yellow-600' : 'text-green-600') }} mt-1">
                                    @if($isOverdue)
                                        {{ $daysDiff }} days overdue ⚠️
                                    @elseif($daysDiff == 0)
                                        Due today! ⏰
                                    @else
                                        {{ $daysDiff }} days left ✓
                                    @endif
                                </p>
                            </div>
                        @endif
                    @endif

                    @if($loan->repaid_date)
                        <div>
                            <span class="text-sm text-gray-600 font-medium">Paid Off Date</span>
                            <p class="text-lg font-semibold text-green-600 mt-1">{{ $loan->repaid_date->format('M d, Y') }}</p>
                        </div>
                    @endif
                </div>

                @if($loan->notes)
                    <div class="mt-6 pt-6 border-t">
                        <span class="text-sm text-gray-600 font-medium">Notes</span>
                        <p class="text-gray-700 mt-2">{{ $loan->notes }}</p>
                    </div>
                @endif
            </div>

            <!-- Financial Summary Card -->
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border-l-4 border-indigo-500 rounded-lg p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Financial Summary</h2>

                <div class="space-y-3">
                    <div>
                        <span class="text-sm text-gray-600">Principal</span>
                        <p class="text-2xl font-bold text-gray-900">
                            KES {{ number_format($loan->principal_amount, 0, '.', ',') }}
                        </p>
                    </div>

                    @if($loan->interest_amount > 0 || $loan->interest_rate > 0)
                        <div class="pt-2 border-t border-indigo-200">
                            <span class="text-sm text-gray-600">Interest</span>
                            <p class="text-lg font-semibold text-gray-900">
                                KES {{ number_format($loan->interest_amount, 0, '.', ',') }}
                                @if($loan->interest_rate > 0)
                                    <span class="text-sm text-gray-500">({{ $loan->interest_rate }}%)</span>
                                @endif
                            </p>
                        </div>
                    @endif

                    <div class="pt-2 border-t-2 border-indigo-300">
                        <span class="text-sm text-gray-600">Total Due</span>
                        <p class="text-3xl font-bold text-indigo-600">
                            KES {{ number_format($loan->total_amount, 0, '.', ',') }}
                        </p>
                    </div>

                    <div class="pt-4 space-y-2 bg-white bg-opacity-50 -mx-6 -mb-6 px-6 py-4 rounded-b-lg">
                        <div class="flex justify-between">
                            <span class="text-gray-700">Paid</span>
                            <span class="font-semibold text-green-600">KES {{ number_format($loan->amount_paid, 0, '.', ',') }}</span>
                        </div>
                        <div class="flex justify-between font-bold text-lg">
                            <span class="text-gray-700">Balance</span>
                            <span class="{{ $loan->balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                                KES {{ number_format($loan->balance, 0, '.', ',') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar -->
                @if($loan->total_amount > 0)
                    <div class="mt-6">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs text-gray-600 font-semibold">Progress</span>
                            @php $percentage = ($loan->amount_paid / $loan->total_amount) * 100; @endphp
                            <span class="text-sm font-bold text-gray-900">{{ number_format($percentage, 1) }}%</span>
                        </div>
                        <div class="w-full bg-gray-300 rounded-full h-3">
                            <div class="bg-green-500 h-3 rounded-full transition-all" style="width: {{ min($percentage, 100) }}%"></div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Payment History -->
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-bold mb-6">Payment History</h2>

            @if($loan->payments && $loan->payments->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-100 border-b-2 border-gray-300">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Date</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Principal</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Interest</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Notes</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                        @foreach($loan->payments as $payment)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-900 font-medium">
                                    {{ $payment->payment_date->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900">
                                    KES {{ number_format($payment->principal_portion, 0, '.', ',') }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900">
                                    KES {{ number_format($payment->interest_portion, 0, '.', ',') }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">
                                    KES {{ number_format($payment->amount, 0, '.', ',') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ $payment->notes ?? '-' }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                        <tr class="font-bold">
                            <td class="px-6 py-4 text-sm text-gray-900">Total Payments</td>
                            <td class="px-6 py-4 text-right text-sm text-gray-900">
                                KES {{ number_format($loan->payments->sum('principal_portion'), 0, '.', ',') }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-gray-900">
                                KES {{ number_format($loan->payments->sum('interest_portion'), 0, '.', ',') }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-gray-900">
                                KES {{ number_format($loan->payments->sum('amount'), 0, '.', ',') }}
                            </td>
                            <td></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="bg-gray-50 border border-gray-200 rounded p-6 text-center">
                    <p class="text-gray-500">No payments recorded yet</p>
                    @if($loan->status === 'active')
                        <a href="{{ route('loans.payment', $loan) }}" class="text-blue-600 hover:text-blue-800 text-sm mt-2">
                            Make the first payment →
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <!-- Delete Action (only for active loans with no payments) -->
        @if($loan->status === 'active' && (!$loan->payments || $loan->payments->count() === 0))
            <div class="mt-8 p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-gray-600 mb-3">Danger Zone</p>
                <form method="POST" action="{{ route('loans.destroy', $loan) }}"
                      onsubmit="return confirm('⚠️ Are you sure? This will delete the loan and remove the disbursement transaction. This action cannot be undone.');"
                      class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-semibold">
                        🗑️ Delete This Loan
                    </button>
                </form>
            </div>
        @endif
    </div>
@endsection
