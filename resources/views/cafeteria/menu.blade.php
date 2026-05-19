{{-- resources/views/cafeteria/menu.blade.php --}}
<x-app-layout>
    <style>
        .menu-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .menu-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .category-tab.active {
            background: linear-gradient(135deg, #f97316, #ef4444);
            color: white;
        }
        .item-row {
            transition: background 0.15s ease;
        }
        .item-row:hover {
            background: rgba(249, 115, 22, 0.04);
        }
        .modal-backdrop {
            backdrop-filter: blur(4px);
        }
        @media (max-width: 640px) {
            .category-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            .category-tabs::-webkit-scrollbar { display: none; }
        }
    </style>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('cafeteria.index') }}"
                   class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Menu Management
                </h2>
            </div>
            <button onclick="openAddModal()"
                    class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Item
            </button>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10 bg-gradient-to-br from-slate-50 via-amber-50 to-orange-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
        <div class="max-w-6xl mx-auto px-3 sm:px-4 lg:px-8">

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="mb-6 px-4 py-3 bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-200 rounded-xl text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif

            {{-- Page Title --}}
            <div class="mb-6">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-1">
                    Menu 🍽️
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $menuItems->flatten()->count() }} items across {{ $menuItems->count() }} categories
                </p>
            </div>

            {{-- Stats Strip --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
                @foreach($categories as $key => $label)
                    @php $count = isset($menuItems[$key]) ? $menuItems[$key]->count() : 0; @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm flex items-center gap-3">
                        <span class="text-2xl">{{ $categoryIcons[$key] }}</span>
                        <div>
                            <p class="text-lg font-bold text-gray-900 dark:text-white leading-none">{{ $count }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $label }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Category Tabs --}}
            <div class="category-tabs flex gap-2 mb-6 pb-1">
                <button onclick="filterCategory('all')"
                        id="tab-all"
                        class="category-tab active flex-shrink-0 px-4 py-2 rounded-full text-sm font-medium border border-transparent transition-all">
                    All Items
                </button>
                @foreach($categories as $key => $label)
                    <button onclick="filterCategory('{{ $key }}')"
                            id="tab-{{ $key }}"
                            class="category-tab flex-shrink-0 px-4 py-2 rounded-full text-sm font-medium bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 transition-all">
                        {{ $categoryIcons[$key] }} {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Menu Sections --}}
            <div class="space-y-6">
                @foreach($categories as $key => $label)
                    @if(isset($menuItems[$key]) && $menuItems[$key]->count() > 0)
                        <div class="category-section" data-category="{{ $key }}">
                            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg overflow-hidden">

                                {{-- Category Header --}}
                                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-orange-50 to-amber-50 dark:from-gray-800 dark:to-gray-800">
                                    <div class="flex items-center gap-3">
                                        <span class="text-2xl">{{ $categoryIcons[$key] }}</span>
                                        <div>
                                            <h3 class="font-bold text-gray-900 dark:text-white text-base">{{ $label }}</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $menuItems[$key]->count() }} item{{ $menuItems[$key]->count() !== 1 ? 's' : '' }}
                                                &middot;
                                                KES {{ number_format($menuItems[$key]->min('unit_price'), 0) }}
                                                –
                                                {{ number_format($menuItems[$key]->max('unit_price'), 0) }}
                                            </p>
                                        </div>
                                    </div>
                                    <span class="text-xs bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300 px-2 py-1 rounded-full font-medium">
                                        {{ $menuItems[$key]->where('is_active', true)->count() }} active
                                    </span>
                                </div>

                                {{-- Items Table --}}
                                <div class="overflow-x-auto">
                                    <table class="w-full">
                                        <thead>
                                        <tr class="text-xs text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                                            <th class="px-5 py-3 text-left font-semibold">Item Name</th>
                                            <th class="px-5 py-3 text-right font-semibold">Price (KES)</th>
                                            <th class="px-5 py-3 text-center font-semibold">Status</th>
                                            <th class="px-5 py-3 text-center font-semibold">Actions</th>
                                        </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                                        @foreach($menuItems[$key] as $item)
                                            <tr class="item-row {{ !$item->is_active ? 'opacity-50' : '' }}">
                                                <td class="px-5 py-3">
                                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                            {{ $item->name }}
                                                        </span>
                                                </td>
                                                <td class="px-5 py-3 text-right">
                                                        <span class="text-sm font-bold text-orange-600 dark:text-orange-400">
                                                            {{ number_format($item->unit_price, 0) }}
                                                        </span>
                                                </td>
                                                <td class="px-5 py-3 text-center">
                                                    @if($item->is_active)
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">
                                                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                                                                Active
                                                            </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 inline-block"></span>
                                                                Hidden
                                                            </span>
                                                    @endif
                                                </td>
                                                <td class="px-5 py-3">
                                                    <div class="flex items-center justify-center gap-2">
                                                        {{-- Edit --}}
                                                        <button
                                                            onclick="openEditModal({{ $item->id }}, '{{ addslashes($item->name) }}', '{{ $item->category }}', {{ $item->unit_price }}, {{ $item->is_active ? 'true' : 'false' }})"
                                                            class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                                                            title="Edit">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                            </svg>
                                                        </button>

                                                        {{-- Hide/Deactivate --}}
                                                        @if($item->is_active)
                                                            <form action="{{ route('cafeteria.menu.destroy', $item) }}" method="POST" class="inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                        onclick="return confirm('Hide \'{{ $item->name }}\' from the menu?')"
                                                                        class="p-1.5 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/30 rounded-lg transition-colors"
                                                                        title="Hide from menu">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                                                    </svg>
                                                                </button>
                                                            </form>
                                                        @else
                                                            {{-- Restore via edit form setting is_active = true --}}
                                                            <form action="{{ route('cafeteria.menu.update', $item) }}" method="POST" class="inline">
                                                                @csrf
                                                                @method('PUT')
                                                                <input type="hidden" name="name" value="{{ $item->name }}">
                                                                <input type="hidden" name="category" value="{{ $item->category }}">
                                                                <input type="hidden" name="unit_price" value="{{ $item->unit_price }}">
                                                                <input type="hidden" name="is_active" value="1">
                                                                <button type="submit"
                                                                        class="p-1.5 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/30 rounded-lg transition-colors"
                                                                        title="Restore to menu">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                                    </svg>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Seed Button (dev only — remove in production) --}}
            @if($menuItems->flatten()->count() === 0)
                <div class="mt-8 text-center">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-10 max-w-md mx-auto">
                        <span class="text-5xl block mb-4">🌱</span>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Menu is empty</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                            Seed the DEL Restaurant menu to get started with all default items.
                        </p>
                        <form action="{{ route('cafeteria.seed-menu') }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="px-6 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-medium text-sm transition-colors">
                                Seed Default Menu
                            </button>
                        </form>
                    </div>
                </div>
            @endif

        </div>
    </div>

    {{-- ===== ADD ITEM MODAL ===== --}}
    <div id="addModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0 bg-black/50" onclick="closeAddModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6 relative z-10">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add Menu Item</h3>
                    <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form action="{{ route('cafeteria.menu.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Item Name</label>
                        <input type="text" name="name" required
                               placeholder="e.g. Ugali Beef"
                               class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-400 focus:border-transparent outline-none transition">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                        <select name="category" required
                                class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-400 focus:border-transparent outline-none transition">
                            <option value="">Select category…</option>
                            @foreach($categories as $key => $label)
                                <option value="{{ $key }}">{{ $categoryIcons[$key] }} {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Price (KES)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400 font-medium">KES</span>
                            <input type="number" name="unit_price" required min="0" step="1"
                                   placeholder="0"
                                   class="w-full pl-12 pr-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-400 focus:border-transparent outline-none transition">
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeAddModal()"
                                class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-sm font-medium transition-colors">
                            Add Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===== EDIT ITEM MODAL ===== --}}
    <div id="editModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0 bg-black/50" onclick="closeEditModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6 relative z-10">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Edit Menu Item</h3>
                    <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form id="editForm" action="" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Item Name</label>
                        <input type="text" id="editName" name="name" required
                               class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-400 focus:border-transparent outline-none transition">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                        <select id="editCategory" name="category" required
                                class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-400 focus:border-transparent outline-none transition">
                            @foreach($categories as $key => $label)
                                <option value="{{ $key }}">{{ $categoryIcons[$key] }} {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Price (KES)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400 font-medium">KES</span>
                            <input type="number" id="editPrice" name="unit_price" required min="0" step="1"
                                   class="w-full pl-12 pr-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-400 focus:border-transparent outline-none transition">
                        </div>
                    </div>

                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" id="editActive" name="is_active" value="1"
                               class="w-4 h-4 rounded text-orange-500 focus:ring-orange-400 border-gray-300 dark:border-gray-600">
                        <label for="editActive" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Active — visible when logging meals
                        </label>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeEditModal()"
                                class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-sm font-medium transition-colors">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-floating-action-button />

    <script>
        // ── Category filter ──────────────────────────────────────────
        function filterCategory(key) {
            const sections = document.querySelectorAll('.category-section');
            const tabs = document.querySelectorAll('.category-tab');

            tabs.forEach(t => {
                t.classList.remove('active');
                t.classList.add('bg-white', 'dark:bg-gray-800', 'text-gray-600', 'dark:text-gray-300',
                    'border-gray-200', 'dark:border-gray-700');
            });

            const activeTab = document.getElementById('tab-' + key);
            if (activeTab) {
                activeTab.classList.add('active');
                activeTab.classList.remove('bg-white', 'dark:bg-gray-800', 'text-gray-600',
                    'dark:text-gray-300', 'border-gray-200', 'dark:border-gray-700');
            }

            sections.forEach(s => {
                if (key === 'all' || s.dataset.category === key) {
                    s.style.display = '';
                } else {
                    s.style.display = 'none';
                }
            });
        }

        // ── Add modal ────────────────────────────────────────────────
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        // ── Edit modal ───────────────────────────────────────────────
        function openEditModal(id, name, category, price, isActive) {
            const baseUrl = "{{ url('cafeteria/menu') }}";
            document.getElementById('editForm').action = baseUrl + '/' + id;
            document.getElementById('editName').value = name;
            document.getElementById('editCategory').value = category;
            document.getElementById('editPrice').value = price;
            document.getElementById('editActive').checked = isActive;

            document.getElementById('editModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Close modals on Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
            }
        });
    </script>

</x-app-layout>
