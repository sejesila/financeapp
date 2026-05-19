{{-- resources/views/cafeteria/show.blade.php --}}
{{-- FIX: all $cafeteria references renamed to $order to match what the controller passes --}}
<x-app-layout>
    <style>
        .read-only-message {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #dc2626;
        }

        .dark .read-only-message {
            background-color: #7f1d1d;
            color: #fecaca;
            border-left-color: #ef4444;
        }

        .action-button-disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }

        .time-remaining {
            font-size: 0.875rem;
            font-weight: 500;
            color: #059669;
        }

        .dark .time-remaining {
            color: #10b981;
        }
    </style>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Meal Details') }}
            </h2>
            <div class="flex gap-3">
                @if($order->isEditable())
                    <a href="{{ route('cafeteria.edit', $order) }}"
                       class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors">
                        Edit
                    </a>
                @else
                    <span class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-400 rounded-lg text-sm font-medium cursor-not-allowed">
                        Edit
                    </span>
                @endif
                <a href="{{ route('cafeteria.index') }}"
                   class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg text-sm font-medium transition-colors">
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12 bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
        <div class="max-w-2xl mx-auto px-3 sm:px-4 lg:px-8">

            {{-- Read-only Alert --}}
            @if(!$order->isEditable())
                <div class="read-only-message">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-lg">🔒</span>
                        <strong>This meal entry is now read-only</strong>
                    </div>
                    <p class="text-sm">
                        Meal entries can only be edited within 1 hour of creation. This entry was created {{ $order->created_at->diffForHumans() }}.
                    </p>
                </div>
            @else
                <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg">
                    <p class="text-sm text-green-800 dark:text-green-200">
                        ✓ This entry is still editable. <span class="time-remaining">{{ $order->getEditTimeRemainingFormatted() }}</span>
                    </p>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                {{-- Header --}}
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 sm:px-8 py-6 sm:py-8 text-white">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold mb-2">{{ $order->order_date->format('l, F j, Y') }}</h1>
                            <p class="text-white/80">
                                <span class="inline-block px-3 py-1 rounded-full text-sm font-medium bg-white/20">
                                    {{ ucfirst($order->meal_time) }} 🍽️
                                </span>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm opacity-80">Total Amount</p>
                            <p class="text-3xl font-bold">KES {{ number_format($order->total_amount, 0) }}</p>
                        </div>
                    </div>
                </div>

                {{-- Content --}}
                <div class="p-6 sm:p-8 space-y-6">

                    {{-- Meal Items --}}
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Items Ordered</h2>
                        <div class="space-y-2">
                            @foreach($order->items as $item)
                                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $item->menuItem->name }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ ucfirst(str_replace('_', ' ', $item->menuItem->category)) }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            <span class="font-semibold">{{ $item->quantity }}</span> × KES {{ number_format($item->unit_price, 0) }}
                                        </p>
                                        <p class="font-bold text-indigo-600 dark:text-indigo-400">
                                            KES {{ number_format($item->subtotal, 0) }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Summary --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 dark:text-gray-400">Subtotal:</span>
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    KES {{ number_format($order->total_amount, 0) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-lg font-bold bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                                <span class="text-gray-900 dark:text-white">Total:</span>
                                <span class="text-indigo-600 dark:text-indigo-400">
                                    KES {{ number_format($order->total_amount, 0) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Notes --}}
                    @if($order->notes)
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-2">Notes</h3>
                            <p class="text-gray-600 dark:text-gray-400 italic">{{ $order->notes }}</p>
                        </div>
                    @endif

                    {{-- Metadata --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-600 dark:text-gray-400 mb-1">Created</p>
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ $order->created_at->format('M d, Y H:i') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-600 dark:text-gray-400 mb-1">Last Updated</p>
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ $order->updated_at->format('M d, Y H:i') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="bg-gray-50 dark:bg-gray-700/50 px-6 sm:px-8 py-4 border-t border-gray-200 dark:border-gray-700 flex gap-3">
                    @if($order->isEditable())
                        <a href="{{ route('cafeteria.edit', $order) }}"
                           class="flex-1 text-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                            ✏️ Edit Meal
                        </a>
                        <form action="{{ route('cafeteria.destroy', $order) }}" method="POST" class="flex-1">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    onclick="return confirm('Are you sure you want to delete this meal entry?')"
                                    class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                                🗑️ Delete Meal
                            </button>
                        </form>
                    @else
                        <button disabled
                                class="flex-1 px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-400 rounded-lg font-medium cursor-not-allowed"
                                title="Edit disabled after 1 hour">
                            ✏️ Edit Meal
                        </button>
                        <button disabled
                                class="flex-1 px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-400 rounded-lg font-medium cursor-not-allowed"
                                title="Delete disabled after 1 hour">
                            🗑️ Delete Meal
                        </button>
                    @endif
                </div>
            </div>

            <div class="mt-6">
                <a href="{{ route('cafeteria.index') }}"
                   class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 font-medium">
                    ← Back to Meals
                </a>
            </div>
        </div>
    </div>

</x-app-layout>
