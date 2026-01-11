<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $clientFund->client_name }} - {{ $clientFund->purpose }}
            </h2>
            <a href="{{ route('client-funds.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Client Funds
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="bg-green-100 text-green-700 p-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Received</p>
                    <p class="text-2xl font-bold text-blue-600">
                        KES {{ number_format($clientFund->amount_received, 0, '.', ',') }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Spent</p>
                    <p class="text-2xl font-bold text-orange-600">
                        KES {{ number_format($clientFund->amount_spent, 0, '.', ',') }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Your Profit</p>
                    <p class="text-2xl font-bold text-green-600">
                        KES {{ number_format($clientFund->profit_amount, 0, '.', ',') }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Remaining</p>
                    <p class="text-2xl font-bold text-purple-600">
                        KES {{ number_format($clientFund->balance, 0, '.', ',') }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Left Column: Details & Actions -->
                <div class="lg:col-span-1 space-y-6">

                    <!-- Fund Details -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold mb-4">Fund Details</h3>

                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Status</p>
                                <span class="inline-block px-3 py-1 text-sm rounded-full mt-1
                                    {{ $clientFund->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $clientFund->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    {{ $clientFund->status === 'partial' ? 'bg-blue-100 text-blue-700' : '' }}">
                                    {{ ucfirst($clientFund->status) }}
                                </span>
                            </div>

                            <div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Type</p>
                                <span class="inline-block px-3 py-1 text-sm rounded-full mt-1
                                    {{ $clientFund->type === 'commission' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $clientFund->type === 'commission' ? '💰 With Profit' : '🔄 No Profit' }}
                                </span>
                            </div>

                            <div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Account</p>
                                <p class="font-medium">{{ $clientFund->account->name }}</p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Received Date</p>
                                <p class="font-medium">{{ $clientFund->received_date->format('M d, Y') }}</p>
                            </div>

                            @if($clientFund->completed_date)
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">Completed Date</p>
                                    <p class="font-medium">{{ $clientFund->completed_date->format('M d, Y') }}</p>
                                </div>
                            @endif

                            @if($clientFund->notes)
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">Notes</p>
                                    <p class="text-sm">{{ $clientFund->notes }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    @if($clientFund->status !== 'completed')
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>

                            <div class="space-y-2">
                                <button
                                    onclick="openModal('expenseModal')"
                                    class="w-full bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded text-sm"
                                    {{ $clientFund->balance <= 0 ? 'disabled' : '' }}
                                >
                                    📝 Record Expense
                                </button>

                                @if($clientFund->type === 'commission')
                                    <button
                                        onclick="openModal('profitModal')"
                                        class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm"
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
                                            class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm"
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
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold">Transaction History</h3>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($clientFund->transactions as $transaction)
                                <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-lg">
                                                    @if($transaction->type === 'receipt') 📥
                                                    @elseif($transaction->type === 'expense') 📤
                                                    @elseif($transaction->type === 'profit') 💰
                                                    @elseif($transaction->type === 'return') 🔄
                                                    @endif
                                                </span>
                                                <span class="font-semibold capitalize">{{ $transaction->type }}</span>
                                                <span class="text-xs text-gray-500">
                                                    {{ $transaction->date->format('M d, Y') }}
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ $transaction->description }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-bold text-lg
                                                {{ $transaction->type === 'receipt' ? 'text-blue-600' : '' }}
                                                {{ $transaction->type === 'expense' ? 'text-orange-600' : '' }}
                                                {{ $transaction->type === 'profit' ? 'text-green-600' : '' }}">
                                                {{ $transaction->type === 'receipt' || $transaction->type === 'return' ? '+' : '-' }}
                                                KES {{ number_format($transaction->amount, 0, '.', ',') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="p-8 text-center text-gray-500">
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
    <div id="expenseModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold mb-4">Record Expense</h3>

            <form method="POST" action="{{ route('client-funds.expense', $clientFund) }}">
                @csrf

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Amount</label>
                    <input
                        type="number"
                        step="0.01"
                        name="amount"
                        max="{{ $clientFund->balance }}"
                        placeholder="Enter amount spent"
                        class="w-full border rounded px-3 py-2"
                        required
                    >
                    <p class="text-xs text-gray-500 mt-1">
                        Available: KES {{ number_format($clientFund->balance, 0, '.', ',') }}
                    </p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Category</label>
                    <select name="category_id" class="w-full border rounded px-3 py-2" required>
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

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Description</label>
                    <input
                        type="text"
                        name="description"
                        placeholder="What did you buy?"
                        class="w-full border rounded px-3 py-2"
                        required
                    >
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Date</label>
                    <input
                        type="date"
                        name="date"
                        value="{{ date('Y-m-d') }}"
                        class="w-full border rounded px-3 py-2"
                        required
                    >
                </div>

                <div class="flex gap-2">
                    <button
                        type="button"
                        onclick="closeModal('expenseModal')"
                        class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="flex-1 bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded"
                    >
                        Record Expense
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Profit Modal -->
    @if($clientFund->type === 'commission')
        <div id="profitModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Record Your Profit</h3>

                <form method="POST" action="{{ route('client-funds.profit', $clientFund) }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Profit Amount</label>
                        <input
                            type="number"
                            step="0.01"
                            name="amount"
                            max="{{ $clientFund->balance }}"
                            placeholder="Enter your profit"
                            class="w-full border rounded px-3 py-2"
                            required
                        >
                        <p class="text-xs text-gray-500 mt-1">
                            Available: KES {{ number_format($clientFund->balance, 0, '.', ',') }}
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Description (Optional)</label>
                        <input
                            type="text"
                            name="description"
                            placeholder="e.g., Commission for laptop purchase"
                            class="w-full border rounded px-3 py-2"
                        >
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Date</label>
                        <input
                            type="date"
                            name="date"
                            value="{{ date('Y-m-d') }}"
                            class="w-full border rounded px-3 py-2"
                            required
                        >
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            onclick="closeModal('profitModal')"
                            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded"
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
