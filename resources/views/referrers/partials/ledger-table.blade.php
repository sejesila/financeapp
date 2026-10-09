{{-- resources/views/referrers/partials/ledger-table.blade.php --}}
@if($rows->isEmpty())
    <p class="text-sm text-gray-500">{{ $empty }}</p>
@else
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Date</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">What happened</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Credit</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Debit</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">{{ $balanceLabel }}</th>
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            @foreach($rows as $row)
                <tr>
                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ $row['date']->format('M j, Y') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">
                        {{ $row['title'] }}

                    </td>
                    <td class="px-4 py-3 text-sm text-right text-green-700 whitespace-nowrap">
                        {{ $row['credit'] > 0 ? '+ ' . number_format($row['credit'], 0) : '' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-right text-red-700 whitespace-nowrap">
                        {{ $row['debit'] > 0 ? '− ' . number_format($row['debit'], 0) : '' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 whitespace-nowrap">
                        {{ number_format($row['balance'], 0) }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
