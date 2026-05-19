{{-- resources/views/cafeteria/edit.blade.php --}}
<x-app-layout>
    <style>
        .item-row {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .remove-btn:hover {
            background-color: rgb(239, 68, 68);
            color: white;
        }

        .category-badge {
            transition: all 0.2s ease;
        }

        {{-- FIX: replaced broken @apply with real CSS --}}
        .category-badge.active {
            background-color: #4f46e5;
            color: #ffffff;
            border-color: #4f46e5;
        }
    </style>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Meal') }}
            </h2>
            <a href="{{ route('cafeteria.index') }}" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400">
                ← Back
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12 bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
        <div class="max-w-4xl mx-auto px-3 sm:px-4 lg:px-8">

            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <h3 class="font-bold mb-2">Please fix the following errors:</h3>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- FIX: $cafeteria renamed to $order to match what the controller passes --}}
            <form action="{{ route('cafeteria.update', $order) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                {{-- Meal Date & Time --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Meal Details</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="order_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date"
                                   id="order_date"
                                   name="order_date"
                                   value="{{ old('order_date', $order->order_date->format('Y-m-d')) }}"
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label for="meal_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Meal Time <span class="text-red-500">*</span>
                            </label>
                            {{-- FIX: option value is strtolower so it always matches the controller's validation rule --}}
                            <select id="meal_time"
                                    name="meal_time"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                <option value="">Select meal time</option>
                                @foreach($mealTimes as $key => $label)
                                    <option value="{{ $key }}"
                                        {{ old('meal_time', $order->meal_time) == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Menu Items Selection --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Select Items</h3>

                    {{-- Category Filter --}}
                    <div class="mb-6 flex flex-wrap gap-2">
                        <button type="button"
                                onclick="filterCategory(null)"
                                class="category-badge active px-4 py-2 rounded-full text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-indigo-500 transition-all"
                                data-category="all">
                            All
                        </button>
                        @foreach($menuItems as $category => $items)
                            <button type="button"
                                    onclick="filterCategory('{{ $category }}')"
                                    class="category-badge px-4 py-2 rounded-full text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-indigo-500 transition-all"
                                    data-category="{{ $category }}">
                                {{ ucfirst(str_replace('_', ' ', $category)) }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Items Grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-96 overflow-y-auto">
                        @foreach($menuItems as $category => $items)
                            @foreach($items as $item)
                                <div class="category-section border border-gray-300 dark:border-gray-600 rounded-lg p-3 hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-all cursor-pointer"
                                     data-category="{{ $category }}"
                                     onclick="addItem({{ $item->id }}, '{{ addslashes($item->name) }}', {{ $item->unit_price }})">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $item->name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                {{ ucfirst(str_replace('_', ' ', $category)) }}
                                            </p>
                                        </div>
                                        <p class="font-bold text-indigo-600 dark:text-indigo-400">
                                            KES {{ number_format($item->unit_price, 0) }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                {{-- Selected Items --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Selected Items</h3>

                    <div id="itemsContainer" class="space-y-3 mb-6">
                        {{-- FIX: data-price added so updateTotal() reads from DOM, not a menuData lookup --}}
                        @foreach($order->items as $index => $item)
                            <div class="item-row flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg"
                                 data-price="{{ $item->unit_price }}">
                                <input type="hidden"
                                       name="items[{{ $index }}][menu_item_id]"
                                       value="{{ $item->cafeteria_menu_item_id }}">
                                <span class="flex-1 font-medium text-gray-900 dark:text-white item-name">
                                    {{ $item->menuItem->name }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="decreaseQty(this)"
                                            class="px-3 py-1 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300">−</button>
                                    <input type="number"
                                           name="items[{{ $index }}][quantity]"
                                           value="{{ $item->quantity }}"
                                           min="1"
                                           class="w-12 text-center border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-white"
                                           onchange="updateTotal()">
                                    <button type="button" onclick="increaseQty(this)"
                                            class="px-3 py-1 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300">+</button>
                                </div>
                                <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 min-w-20 text-right item-subtotal">
                                    KES {{ number_format($item->subtotal, 0) }}
                                </span>
                                <button type="button" onclick="removeItem(this)"
                                        class="remove-btn px-3 py-1 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded hover:bg-red-600 transition-colors">✕</button>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 flex justify-between items-center">
                        <span class="text-lg font-semibold text-gray-900 dark:text-white">Total:</span>
                        <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                            KES <span id="totalAmount">{{ number_format($order->total_amount, 0) }}</span>
                        </span>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Notes (Optional)
                    </label>
                    <textarea id="notes"
                              name="notes"
                              rows="3"
                              placeholder="Any special requests or notes..."
                              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent dark:bg-gray-700 dark:text-white">{{ old('notes', $order->notes) }}</textarea>
                </div>

                {{-- Submit Buttons --}}
                <div class="flex gap-3 justify-end">
                    <a href="{{ route('cafeteria.index') }}"
                       class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors">
                        Update Meal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Track which IDs are in the cart to prevent duplicate rows
        const addedIds = new Set();
        document.querySelectorAll('.item-row input[type="hidden"]').forEach(function(input) {
            addedIds.add(parseInt(input.value));
        });

        let itemIndex = {{ $order->items->count() }};

        function addItem(menuItemId, name, price) {
            // If already present, bump quantity instead of adding a duplicate row
            if (addedIds.has(menuItemId)) {
                const existing = document.querySelector(
                    '.item-row input[type="hidden"][value="' + menuItemId + '"]'
                );
                if (existing) {
                    const qtyInput = existing.closest('.item-row').querySelector('input[type="number"]');
                    qtyInput.value = parseInt(qtyInput.value) + 1;
                    updateTotal();
                }
                return;
            }

            addedIds.add(menuItemId);

            const container = document.getElementById('itemsContainer');
            const html = `
                <div class="item-row flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg" data-price="${price}">
                    <input type="hidden" name="items[${itemIndex}][menu_item_id]" value="${menuItemId}">
                    <span class="flex-1 font-medium text-gray-900 dark:text-white item-name">${escapeHtml(name)}</span>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="decreaseQty(this)" class="px-3 py-1 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300">−</button>
                        <input type="number" name="items[${itemIndex}][quantity]" value="1" min="1"
                               class="w-12 text-center border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-white"
                               onchange="updateTotal()">
                        <button type="button" onclick="increaseQty(this)" class="px-3 py-1 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300">+</button>
                    </div>
                    <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 min-w-20 text-right item-subtotal">KES ${numberFormat(price)}</span>
                    <button type="button" onclick="removeItem(this)" class="remove-btn px-3 py-1 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded hover:bg-red-600 transition-colors">✕</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            itemIndex++;
            updateTotal();
        }

        function increaseQty(btn) {
            const input = btn.parentElement.querySelector('input[type="number"]');
            input.value = parseInt(input.value) + 1;
            updateTotal();
        }

        function decreaseQty(btn) {
            const input = btn.parentElement.querySelector('input[type="number"]');
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                updateTotal();
            }
        }

        function removeItem(btn) {
            const row = btn.closest('.item-row');
            const menuItemId = parseInt(row.querySelector('input[type="hidden"]').value);
            addedIds.delete(menuItemId);
            row.remove();
            updateTotal();
        }

        function updateTotal() {
            let total = 0;
            document.querySelectorAll('.item-row').forEach(function(row) {
                // FIX: read price from data-price — avoids crash if item was deactivated
                const price    = parseFloat(row.dataset.price) || 0;
                const qty      = parseInt(row.querySelector('input[type="number"]').value) || 0;
                const subtotal = price * qty;
                row.querySelector('.item-subtotal').textContent = 'KES ' + numberFormat(subtotal);
                total += subtotal;
            });
            document.getElementById('totalAmount').textContent = numberFormat(total);
        }

        function filterCategory(category) {
            document.querySelectorAll('.category-section').forEach(function(section) {
                section.style.display = (category === null || section.dataset.category === category)
                    ? 'block' : 'none';
            });
            document.querySelectorAll('.category-badge').forEach(function(badge) {
                const isAll   = (category === null && badge.dataset.category === 'all');
                const isMatch = (category !== null && badge.dataset.category === category);
                badge.classList.toggle('active', isAll || isMatch);
            });
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        function numberFormat(num) {
            return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateTotal();
            filterCategory(null);
        });
    </script>

</x-app-layout>
