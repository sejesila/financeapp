@extends('layout')

@section('content')
    <div class="max-w-4xl mx-auto px-4">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold">Transaction Details</h1>
            <a href="{{ route('transactions.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Transactions
            </a>
        </div>

        <!-- Flash Messages -->
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

        <!-- Transaction Details Card -->
        <div class="bg-white shadow rounded-lg p-6">
            <!-- Amount (Featured) -->
            <div class="text-center mb-6 pb-6 border-b">
                <p class="text-sm text-gray-500 mb-2">{{ $transaction->date->format('D, d M Y') }}</p>
                <p class="font-bold text-4xl {{ $transaction->category->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                    {{ $transaction->category->type === 'income' ? '+' : '-' }}
                    KES {{ number_format($transaction->amount, 0, '.', ',') }}
                </p>
                <p class="text-gray-600 mt-2">{{ $transaction->description }}</p>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                @php
                    $details = [
                        'Account' => [
                            'value' => $transaction->account->name,
                            'link' => route('accounts.show', $transaction->account),
                            'class' => 'text-blue-600 hover:text-blue-800 font-semibold'
                        ],
                        'Category' => [
                            'badge' => $transaction->category->name,
                            'badgeClass' => $transaction->category->type === 'income'
                                ? 'bg-green-100 text-green-800'
                                : 'bg-red-100 text-red-800'
                        ],
                        'Payment Method' => [
                            'value' => $transaction->payment_method ?? 'N/A',
                            'show' => $transaction->payment_method
                        ],
                        'Recorded' => [
                            'value' => $transaction->created_at->format('D, d M Y \a\t H:i'),
                            'class' => 'text-gray-600'
                        ]
                    ];
                @endphp

                @foreach($details as $label => $item)
                    @if(!isset($item['show']) || $item['show'])
                        <div class="flex justify-between items-center py-2 border-b">
                            <span class="text-sm text-gray-500">{{ $label }}</span>

                            @if(isset($item['badge']))
                                <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $item['badgeClass'] }}">
                                    {{ $item['badge'] }}
                                </span>
                            @elseif(isset($item['link']))
                                <a href="{{ $item['link'] }}" class="{{ $item['class'] }}">
                                    {{ $item['value'] }}
                                </a>
                            @else
                                <span class="{{ $item['class'] ?? 'font-semibold' }}">
                                    {{ $item['value'] }}
                                </span>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Info Note -->
        <div class="mt-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
            <p class="text-sm text-blue-800">
                <strong>📝 Note:</strong> Transactions are permanent records and cannot be edited or deleted.
                If you made a mistake, create a new correcting transaction with the opposite amount.
            </p>
        </div>
    </div>
@endsection
