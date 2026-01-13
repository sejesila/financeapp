<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit Client Fund
            </h2>
            <a href="{{ route('client-funds.show', $clientFund) }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded p-4 mb-6">
                <p class="text-sm text-yellow-800">
                    ⚠️ You can only edit basic information. Amounts cannot be changed after creation.
                    Delete individual expenses/profits if you need to make corrections.
                </p>
            </div>

            <form method="POST" action="{{ route('client-funds.update', $clientFund) }}" class="bg-white shadow rounded-lg p-6">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Client/Company Name</label>
                    <input type="text" name="client_name" value="{{ old('client_name', $clientFund->client_name) }}"
                           class="w-full border rounded px-4 py-2" required>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2">Purpose</label>
                    <input type="text" name="purpose" value="{{ old('purpose', $clientFund->purpose) }}"
                           class="w-full border rounded px-4 py-2" required>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 font-semibold mb-2">Notes</label>
                    <textarea name="notes" rows="3" class="w-full border rounded px-4 py-2">{{ old('notes', $clientFund->notes) }}</textarea>
                </div>

                <div class="flex justify-between">
                    <a href="{{ route('client-funds.show', $clientFund) }}" class="text-gray-600">Cancel</a>
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded">Update</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
