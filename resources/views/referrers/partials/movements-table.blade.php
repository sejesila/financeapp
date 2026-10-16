{{-- resources/views/referrers/partials/movements-table.blade.php --}}
@if($months->isEmpty())
    <p class="text-sm text-gray-500">{{ $empty }}</p>
@else
    <div x-data="{ outsideOnly: false }">
        <label class="inline-flex items-center gap-2 text-sm text-gray-600 mb-3">
            <input type="checkbox" x-model="outsideOnly" class="rounded border-gray-300">
            Only show movements outside her float
        </label>

        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Account</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">What happened</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">In</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Out</th>
                </tr>
                </thead>

                @foreach($months as $month)
                    <tbody class="bg-white divide-y divide-gray-200 border-t border-gray-200">
                    <tr class="bg-gray-100">
                        <td colspan="5" class="px-4 py-2 text-sm font-semibold text-gray-800">{{ $month['label'] }}</td>
                    </tr>

                    @foreach($month['rows'] as $row)
                        <tr @if($row['in_float']) x-show="!outsideOnly" @endif>
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ $row['date']->format('M j') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                {{ $row['account'] }}
                                @if($row['in_float'])
                                    <span class="ml-1 inline-block rounded bg-green-100 px-1.5 py-0.5 text-xs text-green-700">float</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $row['title'] }}
                                @if($row['unlinked'])
                                    <span class="ml-1 inline-block rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">matched by name</span>
                                @endif
                                @if($row['note'])
                                    <span class="block text-xs text-gray-400">{{ $row['note'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right text-green-700 whitespace-nowrap">
                                {{ $row['in'] > 0 ? '+ ' . number_format($row['in'], 0) : '' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right text-red-700 whitespace-nowrap">
                                {{ $row['out'] > 0 ? '− ' . number_format($row['out'], 0) : '' }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
    </div>
@endif
