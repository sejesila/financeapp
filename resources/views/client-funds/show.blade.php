<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div class="flex-1 min-w-0">
                <h2 class="font-semibold text-base sm:text-lg md:text-xl text-gray-800 dark:text-gray-200 leading-tight truncate">
                    {{ $clientFund->client_name }}
                </h2>
                <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 truncate">
                    {{ $clientFund->purpose }}
                </p>
            </div>
            <a href="{{ route('client-funds.index') }}" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-800 whitespace-nowrap">
                ← Back
            </a>
        </div>
    </x-slot>

    <div class="py-4 sm:py-8">
        <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-4 mb-4 sm:mb-6">
                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Received</p>
                    <p class="text-lg sm:text-2xl font-bold text-blue-600">
                        {{ number_format($clientFund->amount_received, 0) }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Spent</p>
                    <p class="text-lg sm:text-2xl font-bold text-orange-600">
                        {{ number_format($clientFund->amount_spent, 0) }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Profit</p>
                    <p class="text-lg sm:text-2xl font-bold text-green-600">
                        {{ number_format($clientFund->profit_amount, 0) }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Balance</p>
                    <p class="text-lg sm:text-2xl font-bold text-purple-600">
                        {{ number_format($clientFund->balance, 0) }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">

                <!-- Left Column: Details & Actions -->
                <div class="lg:col-span-1 space-y-4 sm:space-y-6">

                    <!-- Fund Details -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 sm:p-6">
                        <h3 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4">Fund Details</h3>
                        <div class="flex gap-2">
                            <a href="{{ route('client-funds.edit', $clientFund) }}"
                               class="text-blue-600 hover:text-blue-800 text-xs">
                                ✏️ Edit
                            </a>
                            @if($clientFund->transactions->whereIn('type', ['expense', 'profit'])->isEmpty())
                                <form method="POST" action="{{ route('client-funds.destroy', $clientFund) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            onclick="return confirm('Are you sure? This will reverse the account balance change.')"
                                            class="text-red-600 hover:text-red-800 text-xs">
                                        🗑️ Delete
                                    </button>
                                </form>
                            @endif
                        </div>

                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Status</p>
                                <span class="inline-block px-2 sm:px-3 py-1 text-xs sm:text-sm rounded-full mt-1
                                    {{ $clientFund->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $clientFund->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    {{ $clientFund->status === 'partial' ? 'bg-blue-100 text-blue-700' : '' }}">
                                    {{ ucfirst($clientFund->status) }}
                                </span>
                            </div>

                            <div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Type</p>
                                <span class="inline-block px-2 sm:px-3 py-1 text-xs sm:text-sm rounded-full mt-1
                                    {{ $clientFund->type === 'commission' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $clientFund->type === 'commission' ? '💰 With Profit' : '🔄 No Profit' }}
                                </span>
                            </div>

                            <div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Account</p>
                                <p class="font-medium text-sm sm:text-base">{{ $clientFund->account->name }}</p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Received Date</p>
                                <p class="font-medium text-sm sm:text-base">{{ $clientFund->received_date->format('M d, Y') }}</p>
                            </div>

                            @if($clientFund->completed_date)
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">Completed Date</p>
                                    <p class="font-medium text-sm sm:text-base">{{ $clientFund->completed_date->format('M d, Y') }}</p>
                                </div>
                            @endif

                            @if($clientFund->notes)
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">Notes</p>
                                    <p class="text-xs sm:text-sm">{{ $clientFund->notes }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    @if($clientFund->status !== 'completed')
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 sm:p-6">
                            <h3 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4">Quick Actions</h3>

                            <div class="space-y-2">
                                <button
                                    onclick="openModal('expenseModal')"
                                    class="w-full bg-orange-500 hover:bg-orange-600 text-white px-4 py-2.5 sm:py-2 rounded text-sm font-medium transition"
                                    {{ $clientFund->balance <= 0 ? 'disabled' : '' }}
                                >
                                    📝 Record Expense
                                </button>

                                @if($clientFund->type === 'commission')
                                    <button
                                        onclick="openModal('profitModal')"
                                        class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2.5 sm:py-2 rounded text-sm font-medium transition"
                                        {{ $clientFund->balance <= 0 ? 'disabled' : '' }}
                                    >
                                        💰 Record Profit
                                    </button>
                                @endif

                                @if($clientFund->balance <= 0)
                                    <form method="POST" action="{{ route('client-funds.complete', $clientFund) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2.5 sm:py-2 rounded text-sm font-medium transition"
                                            onclick="return confirm('Mark this fund as completed?')"
                                        >
                                            ✅ Mark as Completed
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif

                </div>

                <!-- Right Column: Transaction History -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                        <div class="p-3 sm:p-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-base sm:text-lg font-semibold">Transaction History</h3>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($clientFund->transactions as $transaction)
                                <div class="p-3 sm:p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <div class="flex justify-between items-start gap-3">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                                <span class="text-base sm:text-lg">
                                                    @if($transaction->type === 'receipt') 📥
                                                    @elseif($transaction->type === 'expense') 📤
                                                    @elseif($transaction->type === 'profit') 💰
                                                    @elseif($transaction->type === 'return') 🔄
                                                    @endif
                                                </span>
                                                <span class="font-semibold capitalize text-sm sm:text-base">{{ $transaction->type }}</span>
                                                <span class="text-xs text-gray-500">
                                                    {{ $transaction->date->format('M d, Y') }}
                                                </span>
                                            </div>
                                            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 break-words">
                                                {{ $transaction->description }}
                                            </p>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <p class="font-bold text-base sm:text-lg
                                                {{ $transaction->type === 'receipt' ? 'text-blue-600' : '' }}
                                                {{ $transaction->type === 'expense' ? 'text-orange-600' : '' }}
                                                {{ $transaction->type === 'profit' ? 'text-green-600' : '' }}">
                                                {{ $transaction->type === 'receipt' || $transaction->type === 'return' ? '+' : '-' }}
                                                {{ number_format($transaction->amount, 0) }}
                                            </p>
                                            @if($transaction->type === 'expense' || $transaction->type === 'profit')
                                                <form method="POST"
                                                      action="{{ route('client-funds.' . $transaction->type . '.delete', [$clientFund, $transaction]) }}"
                                                      class="mt-1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            onclick="return confirm('Delete this {{ $transaction->type }}?')"
                                                            class="text-xs text-red-600 hover:text-red-800">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="p-6 sm:p-8 text-center text-gray-500 text-sm">
                                    No transactions yet.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Expense Modal -->
    <div id="expenseModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-4 sm:p-6 max-w-md w-full">
            <h3 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4">Record Expense</h3>

            <form method="POST" action="{{ route('client-funds.expense', $clientFund) }}">
                @csrf

                <div class="mb-3 sm:mb-4">
                    <label class="block text-xs sm:text-sm font-medium mb-1 sm:mb-2">Amount</label>
                    <input
                        type="number"
                        step="0.01"
                        name="amount"
                        max="{{ $clientFund->balance }}"
                        placeholder="Enter amount spent"
                        class="w-full border rounded px-3 py-2 text-sm sm:text-base dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                        required
                    >
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Available: KES {{ number_format($clientFund->balance, 0, '.', ',') }}
                    </p>
                </div>

                <div class="mb-3 sm:mb-4">
                    <label class="block text-xs sm:text-sm font-medium mb-1 sm:mb-2">Category</label>
                    <select name="category_id" class="w-full border rounded px-3 py-2 text-sm sm:text-base dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                        <option value="">Select category</option>
                        @php
                            $categories = \App\Models\Category::where('user_id', Auth::id())
                                ->where('type', 'expense')
                                ->where('is_active', true)
                                ->whereNotNull('parent_id')
                                ->get();
                        @endphp
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3 sm:mb-4">
                    <label class="block text-xs sm:text-sm font-medium mb-1 sm:mb-2">Description</label>
                    <input
                        type="text"
                        name="description"
                        placeholder="What did you buy?"
                        class="w-full border rounded px-3 py-2 text-sm sm:text-base dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                        required
                    >
                </div>

                <div class="mb-4 sm:mb-6">
                    <label class="block text-xs sm:text-sm font-medium mb-1 sm:mb-2">Date</label>
                    <input
                        type="date"
                        name="date"
                        value="{{ date('Y-m-d') }}"
                        class="w-full border rounded px-3 py-2 text-sm sm:text-base dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                        required
                    >
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-2">
                    <button
                        type="button"
                        onclick="closeModal('expenseModal')"
                        class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2.5 sm:py-2 rounded text-sm font-medium transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="flex-1 bg-orange-500 hover:bg-orange-600 text-white px-4 py-2.5 sm:py-2 rounded text-sm font-medium transition"
                    >
                        Record Expense
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Profit Modal -->
    @if($clientFund->type === 'commission')
        <div id="profitModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-4 sm:p-6 max-w-md w-full">
                <h3 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4">Record Your Profit</h3>

                <form method="POST" action="{{ route('client-funds.profit', $clientFund) }}">
                    @csrf

                    <div class="mb-3 sm:mb-4">
                        <label class="block text-xs sm:text-sm font-medium mb-1 sm:mb-2">Profit Amount</label>
                        <input
                            type="number"
                            step="0.01"
                            name="amount"
                            max="{{ $clientFund->balance }}"
                            placeholder="Enter your profit"
                            class="w-full border rounded px-3 py-2 text-sm sm:text-base dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                            required
                        >
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Available: KES {{ number_format($clientFund->balance, 0, '.', ',') }}
                        </p>
                    </div>

                    <div class="mb-3 sm:mb-4">
                        <label class="block text-xs sm:text-sm font-medium mb-1 sm:mb-2">Description (Optional)</label>
                        <input
                            type="text"
                            name="description"
                            placeholder="e.g., Commission for laptop purchase"
                            class="w-full border rounded px-3 py-2 text-sm sm:text-base dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                        >
                    </div>

                    <div class="mb-4 sm:mb-6">
                        <label class="block text-xs sm:text-sm font-medium mb-1 sm:mb-2">Date</label>
                        <input
                            type="date"
                            name="date"
                            value="{{ date('Y-m-d') }}"
                            class="w-full border rounded px-3 py-2 text-sm sm:text-base dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                            required
                        >
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row gap-2">
                        <button
                            type="button"
                            onclick="closeModal('profitModal')"
                            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2.5 sm:py-2 rounded text-sm font-medium transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2.5 sm:py-2 rounded text-sm font-medium transition"
                        >
                            Record Profit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('bg-opacity-50')) {
                event.target.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>
