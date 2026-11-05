{{-- resources/views/referrers/partials/ledger.blade.php --}}
{{--
    One component for every statement table. Replaces ledger-table.blade.php and
    movements-table.blade.php (both can be deleted once statement.blade.php uses this).

    Responsive: below the `sm` breakpoint each entry is a stacked card
    (date + amount on the first line, description below, balance last).
    From `sm` up it is the normal column grid.

    Params
      months        Collection of months from ReferrerLedgerService (groupByMonth / allMovements)
      mode          'balance'   -> Date | What happened | Credit | Debit | Running balance
                    'movements' -> Date | Account | What happened | In | Out
      empty         text to show when there are no rows
      balanceLabel  balance mode only: heading of the running-balance column
      creditLabel   balance mode only (default "Credit")
      debitLabel    balance mode only (default "Debit")
      kindStyles    optional [kind => ['label','row','border','badge']] for tints + filter chips
      limit         optional int: only render the newest N months (rest are summarised)
--}}
@php
    $mode         = $mode ?? 'balance';
    $styles       = $kindStyles ?? [];
    $limit        = $limit ?? null;
    $creditLabel  = $creditLabel ?? 'Credit';
    $debitLabel   = $debitLabel ?? 'Debit';
    $balanceLabel = $balanceLabel ?? 'Balance';

    // Full class strings so Tailwind can see them. Only used from `sm` up.
    $cols = $mode === 'balance'
        ? 'sm:grid-cols-[5rem_minmax(0,1fr)_6rem_6rem_7rem]'
        : 'sm:grid-cols-[5rem_10rem_minmax(0,1fr)_6rem_6rem]';

    $hiddenMonths   = 0;
    $broughtForward = null;

    if ($limit && $months->count() > $limit) {
        $hiddenMonths = $months->count() - $limit;
        $months       = $months->slice(-$limit)->values();

        if ($mode === 'balance') {
            $broughtForward = $months->first()['opening'] ?? null;
        }
    }

    $uid = 'lg' . substr(md5(uniqid('', true)), 0, 8);
    $n   = 0;

    $payload = [
        'mode'   => $mode,
        'styles' => $styles,
        'months' => $months->map(function ($m) use (&$n, $mode) {
            return [
                'label'   => $m['label'],
                'opening' => $mode === 'balance' ? (float) $m['opening'] : null,
                'closing' => $mode === 'balance' ? (float) $m['closing'] : null,
                'rows'    => collect($m['rows'])->map(function ($r) use (&$n, $mode) {
                    $base = [
                        'i' => $n++,
                        'd' => $r['date']->format('M j'),
                        't' => $r['title'],
                        'k' => $r['kind'] ?? null,
                    ];

                    return $mode === 'balance'
                        ? $base + ['c' => (float) $r['credit'], 'x' => (float) $r['debit'], 'b' => (float) $r['balance']]
                        : $base + [
                            'a'  => $r['account'],
                            'c'  => (float) $r['in'],
                            'x'  => (float) $r['out'],
                            'fl' => (bool) $r['in_float'],
                            'u'  => (bool) $r['unlinked'],
                            'n'  => $r['note'],
                        ];
                })->values()->all(),
            ];
        })->values()->all(),
    ];
@endphp

@once
    <script>
        window.statementLedger = function (id) {
            const cfg = JSON.parse(document.getElementById(id + '-data').textContent);

            return {
                id: id,
                mode: cfg.mode,
                styles: cfg.styles || {},
                months: cfg.months,
                q: '',
                kind: '',
                outside: false,
                newest: true,
                open: {},
                expanded: {},
                cap: 100,

                init() {
                    // Newest month starts open, everything else collapsed.
                    const last = this.months[this.months.length - 1];
                    if (last) this.open[last.label] = true;
                },

                get kinds() {
                    const seen = {};
                    this.months.forEach(m => m.rows.forEach(r => { if (r.k) seen[r.k] = true; }));
                    return Object.keys(seen).filter(k => this.styles[k]);
                },

                get filtering() {
                    return this.q.trim() !== '' || this.kind !== '' || this.outside;
                },

                matches(r) {
                    if (this.kind && r.k !== this.kind) return false;
                    if (this.outside && r.fl) return false;
                    const q = this.q.trim().toLowerCase();
                    if (!q) return true;
                    return ((r.t || '') + ' ' + (r.a || '') + ' ' + (r.n || '')).toLowerCase().includes(q);
                },

                get view() {
                    const f = this.filtering;
                    let list = this.months.map(m => {
                        const rows = f ? m.rows.filter(r => this.matches(r)) : m.rows;
                        let c = 0, x = 0;
                        rows.forEach(r => { c += r.c; x += r.x; });
                        return {
                            label: m.label, opening: m.opening, closing: m.closing,
                            rows: rows, total: m.rows.length, credits: c, debits: x
                        };
                    });
                    if (f) list = list.filter(m => m.rows.length > 0);
                    return this.newest ? list.slice().reverse() : list;
                },

                get summary() {
                    let n = 0, c = 0, x = 0;
                    this.view.forEach(m => { n += m.rows.length; c += m.credits; x += m.debits; });
                    return { n: n, c: c, x: x };
                },

                isOpen(m) { return this.filtering || !!this.open[m.label]; },
                toggle(m) { if (!this.filtering) this.open[m.label] = !this.open[m.label]; },

                rowsOf(m) {
                    const rows = this.newest ? m.rows.slice().reverse() : m.rows;
                    return this.expanded[m.label] ? rows : rows.slice(0, this.cap);
                },
                moreCount(m) {
                    return this.expanded[m.label] ? 0 : Math.max(0, m.rows.length - this.cap);
                },

                expandAll() { this.months.forEach(m => { this.open[m.label] = true; }); },
                collapseAll() { this.open = {}; },
                clear() { this.q = ''; this.kind = ''; this.outside = false; },

                monthId(m) { return this.id + '-' + m.label.replace(/\s+/g, '-'); },
                jump(label) {
                    if (!label) return;
                    this.clear();
                    this.open[label] = true;
                    this.$nextTick(() => {
                        const el = document.getElementById(this.id + '-' + label.replace(/\s+/g, '-'));
                        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                },

                fmt(n) { return Math.round(n).toLocaleString('en-US'); },
                rowClass(r) { const s = this.styles[r.k]; return s ? s.row : ''; },
                edge(r) { const s = this.styles[r.k]; return s ? s.border : 'border-transparent'; },
                badgeClass(r) { const s = this.styles[r.k]; return s ? s.badge : ''; },
                badgeText(r) { const s = this.styles[r.k]; return s ? s.label : ''; }
            };
        };
    </script>
@endonce

@if($months->isEmpty())
    <p class="text-sm text-gray-500">{{ $empty ?? 'Nothing to show yet.' }}</p>
@else
    <script type="application/json" id="{{ $uid }}-data">{!! json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>

    @if($hiddenMonths > 0)
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            <span>
                {{ $hiddenMonths }} earlier month{{ $hiddenMonths > 1 ? 's' : '' }} not shown.
                @if($broughtForward !== null)
                    Balance brought forward: <strong>KES {{ number_format($broughtForward, 0) }}</strong>.
                @endif
            </span>
            <a href="{{ request()->fullUrlWithQuery(['months' => 'all']) }}" class="font-medium underline">Show all months</a>
        </div>
    @endif

    <div x-data="statementLedger('{{ $uid }}')">

        {{-- Toolbar + column headings are pinned while scrolling on larger screens only.
             On phones the toolbar would eat too much of the viewport, so it scrolls normally.
             If your top nav is sticky, change sm:top-0 to match its height (e.g. sm:top-16). --}}
        <div class="bg-white sm:sticky sm:top-0 sm:z-10">
            <div class="flex flex-wrap items-center gap-2 pb-2 text-sm">
                <input type="search" x-model.debounce.200ms="q" placeholder="Search borrower or description…"
                       class="w-full sm:w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">

                <template x-for="k in kinds" :key="k">
                    <button type="button" @click="kind = (kind === k ? '' : k)"
                            class="rounded px-2 py-0.5 text-xs"
                            :class="styles[k].badge + (kind === k ? ' ring-2 ring-offset-1 ring-gray-400' : ' opacity-70 hover:opacity-100')"
                            x-text="styles[k].label"></button>
                </template>

                @if($mode === 'movements')
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" x-model="outside" class="rounded border-gray-300">
                        Outside her float only
                    </label>
                @endif

                <span class="flex w-full flex-wrap items-center gap-2 text-xs sm:ml-auto sm:w-auto">
                    <template x-if="months.length > 3">
                        <select @change="jump($event.target.value); $event.target.value = ''"
                                class="rounded-md border-gray-300 py-1 text-xs shadow-sm">
                            <option value="">Jump to month…</option>
                            <template x-for="m in view" :key="m.label">
                                <option :value="m.label" x-text="m.label"></option>
                            </template>
                        </select>
                    </template>
                    <button type="button" @click="newest = !newest"
                            class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50"
                            x-text="newest ? 'Newest first' : 'Oldest first'"></button>
                    <button type="button" @click="expandAll()" class="text-indigo-600 hover:underline">Expand all</button>
                    <button type="button" @click="collapseAll()" class="text-indigo-600 hover:underline">Collapse all</button>
                </span>
            </div>

            <div x-show="filtering" x-cloak
                 class="mb-2 flex flex-wrap items-center gap-x-3 rounded bg-indigo-50 px-3 py-1.5 text-xs text-indigo-800">
                <span x-text="summary.n + ' matching entr' + (summary.n === 1 ? 'y' : 'ies')"></span>
                <span x-show="summary.c > 0" class="text-green-700"
                      x-text="(mode === 'balance' ? 'credits ' : 'in ') + fmt(summary.c)"></span>
                <span x-show="summary.x > 0" class="text-red-700"
                      x-text="(mode === 'balance' ? 'debits ' : 'out ') + fmt(summary.x)"></span>
                <button type="button" @click="clear()" class="ml-auto underline">Clear filters</button>
            </div>

            {{-- Column headings: desktop only (phones use stacked cards) --}}
            @if($mode === 'balance')
                <div class="hidden sm:grid {{ $cols }} gap-x-3 rounded-t-lg border border-gray-200 bg-gray-50 py-2 text-xs font-medium text-gray-500">
                    <div class="pl-4">Date</div>
                    <div>What happened</div>
                    <div class="text-right">{{ $creditLabel }}</div>
                    <div class="text-right">{{ $debitLabel }}</div>
                    <div class="pr-4 text-right">{{ $balanceLabel }}</div>
                </div>
            @else
                <div class="hidden sm:grid {{ $cols }} gap-x-3 rounded-t-lg border border-gray-200 bg-gray-50 py-2 text-xs font-medium text-gray-500">
                    <div class="pl-4">Date</div>
                    <div>Account</div>
                    <div>What happened</div>
                    <div class="text-right">In</div>
                    <div class="pr-4 text-right">Out</div>
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 sm:rounded-t-none sm:border-t-0">
            <p x-show="filtering && view.length === 0" x-cloak class="px-4 py-6 text-center text-sm text-gray-500">
                Nothing matches those filters.
            </p>

            <template x-for="m in view" :key="m.label">
                <div :id="monthId(m)" class="border-t border-gray-200 first:border-t-0">

                    {{-- Month header: click to open / close. Wraps onto two lines on phones. --}}
                    <button type="button" @click="toggle(m)"
                            class="flex w-full flex-wrap items-center justify-between gap-x-3 gap-y-1 bg-gray-100 px-4 py-2.5 text-left hover:bg-gray-200">
                        <span class="flex min-w-0 items-center gap-2">
                            <svg class="h-3 w-3 shrink-0 text-gray-400 transition-transform" :class="{ 'rotate-90': isOpen(m) }"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <span class="whitespace-nowrap text-sm font-semibold text-gray-800" x-text="m.label"></span>
                            <span class="whitespace-nowrap text-xs text-gray-500"
                                  x-text="filtering ? m.rows.length + ' of ' + m.total : m.total + (m.total === 1 ? ' entry' : ' entries')"></span>
                        </span>
                        <span class="flex flex-wrap items-center gap-x-4 gap-y-0.5 whitespace-nowrap pl-5 text-xs sm:pl-0">
                            <span x-show="m.credits > 0" class="text-green-700" x-text="'+ ' + fmt(m.credits)"></span>
                            <span x-show="m.debits > 0" class="text-red-700" x-text="'− ' + fmt(m.debits)"></span>
                            <span x-show="mode === 'balance' && !filtering" class="font-medium text-gray-700"
                                  x-text="'Closing ' + fmt(m.closing)"></span>
                        </span>
                    </button>

                    <div x-show="isOpen(m)" x-cloak>
                        <div x-show="mode === 'balance' && !filtering"
                             class="bg-gray-50 px-4 py-1.5 text-xs text-gray-500"
                             x-text="newest ? 'Closing balance ' + fmt(m.closing) : 'Opening balance ' + fmt(m.opening)"></div>

                        <template x-for="r in rowsOf(m)" :key="r.i">
                            <div>
                                @if($mode === 'balance')
                                    {{-- Phone: stacked card --}}
                                    <div class="border-l-4 border-t border-t-gray-100 px-3 py-2 text-sm sm:hidden" :class="rowClass(r) + ' ' + edge(r)">
                                        <div class="flex items-start justify-between gap-3">
                                            <span class="whitespace-nowrap text-xs text-gray-500" x-text="r.d"></span>
                                            <span class="flex flex-col items-end whitespace-nowrap font-medium">
                                            <span x-show="r.c > 0" class="text-green-700" x-text="'+ ' + fmt(r.c)"></span>
                                            <span x-show="r.x > 0" class="text-red-700" x-text="'− ' + fmt(r.x)"></span>
                                        </span>
                                        </div>
                                        <div class="mt-0.5 break-words text-gray-900" x-text="r.t"></div>
                                        <div class="mt-0.5 text-xs text-gray-500">
                                            Balance <span class="font-medium tabular-nums text-gray-700" x-text="fmt(r.b)"></span>
                                        </div>
                                    </div>

                                    {{-- Desktop: grid row --}}
                                    <div class="hidden items-start gap-x-3 border-t border-gray-100 text-sm sm:grid {{ $cols }}" :class="rowClass(r)">
                                        <div class="whitespace-nowrap border-l-4 py-2 pl-3 text-gray-600" :class="edge(r)" x-text="r.d"></div>
                                        <div class="min-w-0 break-words py-2 text-gray-900" x-text="r.t"></div>
                                        <div class="whitespace-nowrap py-2 text-right text-green-700" x-text="r.c > 0 ? '+ ' + fmt(r.c) : ''"></div>
                                        <div class="whitespace-nowrap py-2 text-right text-red-700" x-text="r.x > 0 ? '− ' + fmt(r.x) : ''"></div>
                                        <div class="whitespace-nowrap py-2 pr-4 text-right font-medium tabular-nums text-gray-900" x-text="fmt(r.b)"></div>
                                    </div>
                                @else
                                    {{-- Phone: stacked card --}}
                                    <div class="border-l-4 border-t border-t-gray-100 px-3 py-2 text-sm sm:hidden" :class="rowClass(r) + ' ' + edge(r)">
                                        <div class="flex items-start justify-between gap-3">
                                        <span class="min-w-0 text-xs text-gray-500">
                                            <span x-text="r.d"></span>
                                            <span>·</span>
                                            <span x-text="r.a"></span>
                                            <span x-show="r.fl" class="ml-1 inline-block rounded bg-green-100 px-1.5 py-0.5 text-xs text-green-700">float</span>
                                        </span>
                                            <span class="flex flex-col items-end whitespace-nowrap font-medium">
                                            <span x-show="r.c > 0" class="text-green-700" x-text="'+ ' + fmt(r.c)"></span>
                                            <span x-show="r.x > 0" class="text-red-700" x-text="'− ' + fmt(r.x)"></span>
                                        </span>
                                        </div>
                                        <div class="mt-0.5 break-words text-gray-900">
                                            <span x-text="r.t"></span>
                                            <span x-show="r.k && styles[r.k]" class="ml-1 inline-block rounded px-1.5 py-0.5 text-xs"
                                                  :class="badgeClass(r)" x-text="badgeText(r)"></span>
                                            <span x-show="r.u" class="ml-1 inline-block rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">matched by name</span>
                                        </div>
                                        <div x-show="r.n" class="mt-0.5 break-words text-xs text-gray-400" x-text="r.n"></div>
                                    </div>

                                    {{-- Desktop: grid row --}}
                                    <div class="hidden items-start gap-x-3 border-t border-gray-100 text-sm sm:grid {{ $cols }}" :class="rowClass(r)">
                                        <div class="whitespace-nowrap border-l-4 py-2 pl-3 text-gray-600" :class="edge(r)" x-text="r.d"></div>
                                        <div class="min-w-0 break-words py-2 text-gray-700">
                                            <span x-text="r.a"></span>
                                            <span x-show="r.fl" class="ml-1 inline-block rounded bg-green-100 px-1.5 py-0.5 text-xs text-green-700">float</span>
                                        </div>
                                        <div class="min-w-0 break-words py-2 text-gray-900">
                                            <span x-text="r.t"></span>
                                            <span x-show="r.k && styles[r.k]" class="ml-1 inline-block rounded px-1.5 py-0.5 text-xs"
                                                  :class="badgeClass(r)" x-text="badgeText(r)"></span>
                                            <span x-show="r.u" class="ml-1 inline-block rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">matched by name</span>
                                            <span x-show="r.n" class="block text-xs text-gray-400" x-text="r.n"></span>
                                        </div>
                                        <div class="whitespace-nowrap py-2 text-right text-green-700" x-text="r.c > 0 ? '+ ' + fmt(r.c) : ''"></div>
                                        <div class="whitespace-nowrap py-2 pr-4 text-right text-red-700" x-text="r.x > 0 ? '− ' + fmt(r.x) : ''"></div>
                                    </div>
                                @endif
                            </div>
                        </template>

                        <button type="button" x-show="moreCount(m) > 0" x-cloak @click="expanded[m.label] = true"
                                class="w-full border-t border-gray-100 py-2 text-xs text-indigo-600 hover:bg-gray-50"
                                x-text="'Show ' + moreCount(m) + ' more'"></button>

                        <div x-show="mode === 'balance' && !filtering"
                             class="border-t border-gray-100 bg-gray-50 px-4 py-1.5 text-xs text-gray-500"
                             x-text="newest ? 'Opening balance ' + fmt(m.opening) : 'Closing balance ' + fmt(m.closing)"></div>
                    </div>
                </div>
            </template>
        </div>
    </div>
@endif
