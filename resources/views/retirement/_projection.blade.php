{{-- Retirement projection. Include from retirement/index.blade.php: @include('retirement._projection', ['funds' => $funds]) --}}
@if($funds->isNotEmpty())
    @php
        $projectionFunds = $funds->map(fn ($f) => [
            'name'    => $f->name,
            'balance' => round($f->balance(), 2),
            'monthly' => $f->monthlyRunRate(),
        ])->values();
        $pInput = 'mt-1 w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm';
    @endphp

    <div id="projection" data-funds='@json($projectionFunds)'
         class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 sm:p-6 space-y-4">
        <div>
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">📊 Retirement projection</h3>
            <p class="text-xs text-gray-500 mt-1">
                An estimate, not a forecast. It starts from your current balances and keeps adding your monthly
                contributions, growing them each year. Change any input and the result updates.
            </p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs text-gray-600 dark:text-gray-400">
            <label class="block">Your age
                <input type="number" min="18" max="80" step="1" data-p="age" class="{{ $pInput }}">
            </label>
            <label class="block">Retire at
                <input type="number" min="19" max="80" step="1" data-p="retire" class="{{ $pInput }}">
            </label>
            <label class="block">Return % / year
                <input type="number" min="0" max="30" step="0.5" data-p="ret" class="{{ $pInput }}">
            </label>
            <label class="block">Contribution growth %
                <input type="number" min="0" max="30" step="0.5" data-p="growth" class="{{ $pInput }}">
            </label>
            <label class="block">Inflation %
                <input type="number" min="0" max="30" step="0.5" data-p="infl" class="{{ $pInput }}">
            </label>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-gray-600 dark:text-gray-400">
            @foreach($projectionFunds as $i => $pf)
                <label class="block">{{ $pf['name'] }}: monthly contribution (employee + employer)
                    <input type="number" min="0" step="0.01" data-monthly="{{ $i }}"
                           value="{{ $pf['monthly'] > 0 ? $pf['monthly'] : '' }}" placeholder="0"
                           class="{{ $pInput }}">
                    <span class="block mt-0.5 text-[11px] text-gray-400">
                        {{ $pf['monthly'] > 0 ? 'Prefilled from your last 3 recorded months.' : 'Nothing recorded yet. Enter your monthly total.' }}
                    </span>
                </label>
            @endforeach
        </div>

        <div id="proj-result"></div>
    </div>

    <script>
        (function () {
            const root = document.getElementById('projection');
            if (!root) return;

            const FUNDS    = JSON.parse(root.dataset.funds);
            const defaults = { age: 30, retire: 60, ret: 9, growth: 5, infl: 5 };
            const out      = document.getElementById('proj-result');
            const inputs   = {};
            const monthly  = Array.from(root.querySelectorAll('[data-monthly]'));

            let saved = {};
            try { saved = JSON.parse(localStorage.getItem('retirementProjection') || '{}'); } catch (e) {}

            root.querySelectorAll('[data-p]').forEach(function (el) {
                inputs[el.dataset.p] = el;
                el.value = saved[el.dataset.p] !== undefined ? saved[el.dataset.p] : defaults[el.dataset.p];
            });

            const num = function (el, d) { const v = parseFloat(el.value); return isNaN(v) ? d : v; };
            const esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
            const fmt = function (v) {
                if (v >= 1e6) return 'KES ' + (v / 1e6).toFixed(1) + 'M';
                return 'KES ' + Math.round(v).toLocaleString('en-US');
            };

            // Monthly compounding; contributions are added at month end and grow once a year.
            function project(funds, years, rate, growth) {
                const mr    = Math.pow(1 + rate, 1 / 12) - 1;
                const state = funds.map(function (f) { return { bal: f.balance, m: f.monthly }; });
                const series = [state.reduce(function (s, f) { return s + f.bal; }, 0)];

                for (let y = 0; y < years; y++) {
                    for (let mo = 0; mo < 12; mo++) {
                        state.forEach(function (f) { f.bal = f.bal * (1 + mr) + f.m; });
                    }
                    state.forEach(function (f) { f.m *= (1 + growth); });
                    series.push(state.reduce(function (s, f) { return s + f.bal; }, 0));
                }
                return { series: series, perFund: state.map(function (f) { return f.bal; }) };
            }

            function render() {
                const age    = num(inputs.age, defaults.age);
                const retire = num(inputs.retire, defaults.retire);
                const r      = num(inputs.ret, defaults.ret) / 100;
                const g      = num(inputs.growth, defaults.growth) / 100;
                const infl   = num(inputs.infl, defaults.infl) / 100;
                const years  = Math.round(retire - age);

                try {
                    const s = {};
                    Object.keys(inputs).forEach(function (k) { s[k] = inputs[k].value; });
                    localStorage.setItem('retirementProjection', JSON.stringify(s));
                } catch (e) {}

                if (years < 1) {
                    out.innerHTML = '<p class="text-sm text-amber-600">Retirement age must be later than your current age.</p>';
                    return;
                }

                const funds = FUNDS.map(function (f, i) {
                    return { name: f.name, balance: f.balance, monthly: num(monthly[i], 0) };
                });
                const real = function (v, y) { return v / Math.pow(1 + infl, y); };
                const base = project(funds, years, r, g);
                const end  = base.series[years];

                const scenarios = [Math.max(0, r - 0.02), r, r + 0.02].map(function (rate) {
                    const res = project(funds, years, rate, g).series[years];
                    return { rate: rate, nominal: res, real: real(res, years) };
                });

                let html = '';
                html += '<div class="rounded-lg bg-indigo-50 dark:bg-indigo-900/20 p-4">'
                    +  '<p class="text-xs text-gray-600 dark:text-gray-400">At age ' + retire + ' (in ' + years + ' years)</p>'
                    +  '<p class="text-3xl font-bold text-indigo-700 dark:text-indigo-300">' + fmt(end) + '</p>'
                    +  '<p class="text-sm text-gray-600 dark:text-gray-400">About ' + fmt(real(end, years)) + ' in today\'s money</p>'
                    +  '</div>';

                html += '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">';

                html += '<div><p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Return scenarios</p>'
                    +  '<table class="w-full text-xs"><thead class="text-gray-500"><tr>'
                    +  '<th class="text-left py-1">Return</th><th class="text-right">At ' + retire + '</th><th class="text-right">Today\'s money</th></tr></thead>'
                    +  '<tbody class="text-gray-800 dark:text-gray-200 divide-y divide-gray-100 dark:divide-gray-700">';
                scenarios.forEach(function (sc, i) {
                    html += '<tr' + (i === 1 ? ' class="font-semibold"' : '') + '><td class="py-1">' + (sc.rate * 100).toFixed(1).replace(/\.0$/, '') + '%</td>'
                        +  '<td class="text-right">' + fmt(sc.nominal) + '</td><td class="text-right">' + fmt(sc.real) + '</td></tr>';
                });
                html += '</tbody></table></div>';

                html += '<div><p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Along the way</p>'
                    +  '<table class="w-full text-xs"><thead class="text-gray-500"><tr>'
                    +  '<th class="text-left py-1">Age</th><th class="text-right">Balance</th><th class="text-right">Today\'s money</th></tr></thead>'
                    +  '<tbody class="text-gray-800 dark:text-gray-200 divide-y divide-gray-100 dark:divide-gray-700">';
                const marks = [];
                for (let y = 5; y < years; y += 5) marks.push(y);
                marks.push(years);
                marks.forEach(function (y) {
                    html += '<tr><td class="py-1">' + (age + y) + '</td><td class="text-right">' + fmt(base.series[y]) + '</td>'
                        +  '<td class="text-right">' + fmt(real(base.series[y], y)) + '</td></tr>';
                });
                html += '</tbody></table></div></div>';

                html += '<div class="text-xs text-gray-500">By fund at ' + retire + ': ';
                html += funds.map(function (f, i) { return esc(f.name) + ' ' + fmt(base.perFund[i]); }).join(' · ');
                html += '</div>';

                out.innerHTML = html;
            }

            root.addEventListener('input', render);
            render();
        })();
    </script>
@endif
