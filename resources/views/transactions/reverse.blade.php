@extends('layout')

@section('content')
    <div class="max-w-2xl mx-auto px-4">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold">Reverse Transaction</h1>
            <a href="{{ route('transactions.show', $transaction) }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back
            </a>
        </div>

        <!-- Original Transaction -->
        <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded mb-6">
            <h2 class="font-bold text-blue-900 mb-2">Original Transaction</h2>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span>Date:</span>
                    <span class="font-semibold">{{ $transaction->date->format('M d, Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Description:</span>
                    <span class="font-semibold">{{ $transaction->description }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Amount:</span>
                    <span class="font-bold">{{ number_format($transaction->amount, 0, '.', ',') }}</span>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('transactions.reverse.store', $transaction) }}" class="bg-white shadow rounded-lg p-6">
            @csrf

            <div class="mb-4">
                <label for="reversal_date" class="block text-gray-700 font-semibold mb-2">
                    Reversal Date <span class="text-red-500">*</span>
                </label>
                <input type="date" name="reversal_date" id="reversal_date"
                       value="{{ old('reversal_date', date('Y-m-d')) }}"
                       class="w-full border rounded px-4 py-2" required>
            </div>

            <div class="mb-6">
                <label for="reversal_reason" class="block text-gray-700 font-semibold mb-2">
                    Reason <span class="text-red-500">*</span>
                </label>
                <textarea name="reversal_reason" id="reversal_reason" rows="4"
                          class="w-full border rounded px-4 py-2"
                          placeholder="e.g., Duplicate entry, Incorrect amount..." required></textarea>
            </div>

            <div class="flex gap-4">
                <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded hover:bg-yellow-700">
                    Reverse Transaction
                </button>
                <a href="{{ route('transactions.show', $transaction) }}"
                   class="bg-gray-300 px-6 py-2 rounded hover:bg-gray-400">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
