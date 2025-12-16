@extends('layout')

@section('content')
    <div class="max-w-3xl mx-auto mt-10">
        <div class="flex justify-between mb-6">
            <h1 class="text-2xl font-bold">Categories</h1>
            <a href="{{ route('categories.create') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded">New Category</a>
        </div>

        @if(session('success'))
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <table class="w-full border">
            <thead class="bg-gray-200">
            <tr>
                <th class="border p-2 text-left">Name</th>
                <th class="border p-2 text-left">Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach($categories as $category)
                <tr>
                    <td class="border p-2">{{ $category->name }}</td>
                    <td class="border p-2">
                        <a href="{{ route('categories.edit', $category) }}"
                           class="text-blue-600">Edit</a>

                        <form action="{{ route('categories.destroy', $category) }}"
                              method="POST" class="inline-block"
                              onsubmit="return confirm('Delete this category?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600 ml-3">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
