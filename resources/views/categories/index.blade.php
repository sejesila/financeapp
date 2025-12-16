<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Categories
            </h2>

            <a href="{{ route('categories.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">
                + New Category
            </a>
        </div>
    </x-slot>

    <div class="mx-auto mt-8 max-w-3xl space-y-6">

        {{-- Success Message --}}
        @if(session('success'))
            <div class="rounded-md bg-green-100 px-4 py-3 text-green-700">
                {{ session('success') }}
            </div>
        @endif

        {{-- Categories Table --}}
        <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">Name</th>
                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                </tr>
                </thead>

                <tbody class="divide-y">
                @forelse($categories as $category)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            {{ $category->name }}
                        </td>

                        <td class="px-4 py-3 text-right space-x-3">
                            <a href="{{ route('categories.edit', $category) }}"
                               class="text-blue-600 hover:text-blue-800 font-medium">
                                Edit
                            </a>

                            <form action="{{ route('categories.destroy', $category) }}"
                                  method="POST"
                                  class="inline"
                                  onsubmit="return confirm('Delete this category?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-red-600 hover:text-red-800 font-medium">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-4 py-6 text-center text-gray-500">
                            No categories yet.
                            <a href="{{ route('categories.create') }}"
                               class="text-blue-600 hover:underline ml-1">
                                Create one
                            </a>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
