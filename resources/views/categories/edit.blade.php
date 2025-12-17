<x-app-layout>
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

            <button class="bg-blue-600 text-white px-4 py-2 rounded">Update</button>
        </form>
    </div>
    </x-app-layout>
