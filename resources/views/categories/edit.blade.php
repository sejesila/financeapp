<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Category') }}
            </h2>
            <a href="{{ route('categories.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Categories
            </a>
        </div>
    </x-slot>

    <div class="max-w-xl mx-auto mt-10">
        <h1 class="text-2xl font-bold mb-6">Edit Category</h1>

        <form action="{{ route('categories.update', $category) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block mb-1 font-medium">Category Name</label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}"
                       class="w-full border p-2 rounded">
                @error('name')
                <div class="text-red-600 text-sm">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block mb-1 font-medium">Type</label>
                <select name="type" class="w-full border p-2 rounded">
                    <option value="income" @selected(old('type', $category->type) === 'income')>Income</option>
                    <option value="expense" @selected(old('type', $category->type) === 'expense')>Expense</option>
                    <option value="liability" @selected(old('type', $category->type) === 'liability')>Liability</option>
                </select>
                @error('type')
                <div class="text-red-600 text-sm">{{ $message }}</div>
                @enderror
            </div>

            <button class="bg-blue-600 text-white px-4 py-2 rounded">Update</button>
        </form>
    </div>
</x-app-layout>
