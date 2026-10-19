{{-- resources/views/loans-given/partials/cost-drag.blade.php
     Include after the stats grid:  @include('loans-given.partials.cost-drag')
     Expects $costBySource (array) and $statsLabel (string). --}}
@if(!empty($costBySource))
    <div class="mb-6 border border-gray-200 rounded-lg overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs sm:text-sm font-medium text-gray-700">
                Transaction costs vs gross profit — {{ $statsLabel }}
            </span>
            @if(($costBySource['total']['cost_pct'] ?? null) !== null)
                <span class="text-xs text-gray-500">
                    Overall: costs eat {{ $costBySource['total']['cost_pct'] }}% of interest
                </span>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                <thead class="bg-white text-[10px] sm:text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-2 text-left">Source</th>
                    <th class="px-4 py-2 text-right">Gross profit</th>
                    <th class="px-4 py-2 text-right">Txn costs</th>
                    <th class="px-4 py-2 text-right">Costs eat</th>
                    <th class="px-4 py-2 text-right">After costs</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @foreach(['referred' => 'Referred loans', 'unreferred' => 'Unreferred loans'] as $k => $label)
                    @php $r = $costBySource[$k]; @endphp
                    <tr>
                        <td class="px-4 py-2 font-medium text-gray-800">
                            {{ $label }}
                            <span class="block text-[10px] font-normal text-gray-400">{{ $r['loans'] }} disbursed</span>
                        </td>
                        <td class="px-4 py-2 text-right">KES {{ number_format($r['interest'], 0) }}</td>
                        <td class="px-4 py-2 text-right text-orange-700">KES {{ number_format($r['costs'], 0) }}</td>
                        <td class="px-4 py-2 text-right font-semibold {{ ($r['cost_pct'] ?? 0) > 15 ? 'text-red-600' : 'text-gray-900' }}">
                            {{ $r['cost_pct'] !== null ? $r['cost_pct'] . '%' : '—' }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            KES {{ number_format($r['net'], 0) }}
                            @if($k === 'referred' && $r['referrer_cut'] > 0)
                                <span class="block text-[10px] text-purple-600">
                                    KES {{ number_format($r['net_after_cut'], 0) }} after referrer share owed
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                @php
                    $t = $costBySource['total'];
                    $tLoans = $costBySource['referred']['loans'] + $costBySource['unreferred']['loans'];
                @endphp
                <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                <tr class="font-semibold">
                    <td class="px-4 py-2 text-gray-900">
                        All loans
                        <span class="block text-[10px] font-normal text-gray-400">{{ $tLoans }} disbursed</span>
                    </td>
                    <td class="px-4 py-2 text-right">KES {{ number_format($t['interest'], 0) }}</td>
                    <td class="px-4 py-2 text-right text-orange-700">KES {{ number_format($t['costs'], 0) }}</td>
                    <td class="px-4 py-2 text-right {{ ($t['cost_pct'] ?? 0) > 15 ? 'text-red-600' : 'text-gray-900' }}">
                        {{ $t['cost_pct'] !== null ? $t['cost_pct'] . '%' : '—' }}
                    </td>
                    <td class="px-4 py-2 text-right">KES {{ number_format($t['interest'] - $t['costs'], 0) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
