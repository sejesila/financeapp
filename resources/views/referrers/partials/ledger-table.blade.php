{{-- resources/views/referrers/partials/ledger-table.blade.php --}}
@if($months->isEmpty())
    <p class="text-sm text-gray-500">{{ $empty }}</p>
@else
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="min-w-full">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Date</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">What happened</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Credit</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Debit</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">{{ $balanceLabel }}</th>
            </tr>
            </thead>

            @foreach($months as $month)
                <tbody class="bg-white divide-y divide-gray-200 border-t border-gray-200">
                <tr class="bg-gray-100">
                    <td colspan="5" class="px-4 py-2 text-sm font-semibold text-gray-800">{{ $month['label'] }}</td>
                </tr>

                <tr class="bg-gray-50">
                    <td></td>
                    <td class="px-4 py-2 text-xs text-gray-500">Opening balance</td>
                    <td></td>
                    <td></td>
                    <td class="px-4 py-2 text-sm text-right text-gray-600 whitespace-nowrap">{{ number_format($month['opening'], 0) }}</td>
                </tr>

                @foreach($month['rows'] as $row)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ $row['date']->format('M j') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $row['title'] }}</td>
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

                <tr class="bg-gray-50 font-semibold">
                    <td></td>
                    <td class="px-4 py-2 text-xs text-gray-700">Closing balance</td>
                    <td class="px-4 py-2 text-sm text-right text-green-700 whitespace-nowrap">
                        {{ $month['credits'] > 0 ? '+ ' . number_format($month['credits'], 0) : '' }}
                    </td>
                    <td class="px-4 py-2 text-sm text-right text-red-700 whitespace-nowrap">
                        {{ $month['debits'] > 0 ? '− ' . number_format($month['debits'], 0) : '' }}
                    </td>
                    <td class="px-4 py-2 text-sm text-right text-gray-900 whitespace-nowrap">{{ number_format($month['closing'], 0) }}</td>
                </tr>
                </tbody>
            @endforeach
        </table>
    </div>
@endif
