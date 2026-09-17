<x-app-layout>
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    @if(session('success'))
                        <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4">
                            <p class="text-sm text-green-700">{{ session('success') }}</p>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4">
                            <p class="text-sm text-red-700">{{ session('error') }}</p>
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4">
                            <ul class="list-disc pl-5 space-y-1 text-sm text-red-700">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-800">Reconcile {{ $referrer->name }}'s Float</h2>
                            <p class="text-sm text-gray-500 mt-1">
                                Money she's collected on your behalf and is holding — not her own referral commission.
                            </p>
                        </div>
                        <a href="{{ route('referrers.show', $referrer->id) }}"
                           class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                            Back
                        </a>
                    </div>

                    @if(!$floatAccount)
                        <div class="bg-amber-50 border-l-4 border-amber-400 p-4">
                            <p class="text-sm text-amber-800">
                                {{ $referrer->name }} doesn't have a dedicated float account yet. Create an account
                                of type <code class="bg-amber-100 px-1 rounded">referrer_float</code> and link it to
                                her via its <code class="bg-amber-100 px-1 rounded">referrer_id</code> before using
                                this page.
                            </p>
                        </div>
                    @else
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-700">{{ $floatAccount->name }} balance</span>
                                <span class="text-lg font-semibold text-gray-900">
                                    KES {{ number_format($floatAccount->current_balance, 0) }}
                                </span>
                            </div>
                        </div>

                        @if($pendingLoans->isEmpty())
                            <div class="bg-green-50 border-l-4 border-green-400 p-4">
                                <p class="text-sm text-green-700">
                                    Nothing pending — every loan of hers with interest in the float has been fully reconciled.
                                </p>
                            </div>
                        @else
                            <form method="POST" action="{{ route('referrers.float.reconcile', $referrer) }}" class="space-y-6"
                                  x-data="{
                                      allocations: {
                                          @foreach($pendingLoans as $loan)
                                              {{ $loan->id }}: 0,
                                          @endforeach
                                      },
                                      get total() {
                                          return Object.values(this.allocations).reduce((sum, v) => sum + (parseFloat(v) || 0), 0);
                                      },
                                      fillFull(loanId, pending) {
                                          this.allocations[loanId] = pending;
                                      }
                                  }">
                                @csrf

                                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrower</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pending in Float</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Covered Now</th>
                                        </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($pendingLoans as $loan)
                                            <tr>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    {{ $loan->borrower_name }}
                                                    <span class="block text-xs text-gray-400">
                                                        {{ $loan->status === 'active' ? 'Active loan' : ucfirst($loan->status) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    KES {{ number_format($loan->pending_float_interest, 0) }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <input type="number" step="0.01" min="0"
                                                               max="{{ $loan->pending_float_interest }}"
                                                               name="allocations[{{ $loan->id }}]"
                                                               x-model="allocations[{{ $loan->id }}]"
                                                               class="w-32 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                        <button type="button"
                                                                @click="fillFull({{ $loan->id }}, {{ $loan->pending_float_interest }})"
                                                                class="text-xs text-indigo-600 hover:text-indigo-900 whitespace-nowrap">
                                                            Fill full amount
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="bg-gray-50 rounded-lg p-4 flex justify-between items-center">
                                    <span class="text-sm font-medium text-gray-700">Total being reconciled</span>
                                    <span class="text-lg font-semibold text-gray-900" x-text="'KES ' + total.toLocaleString('en-US', {maximumFractionDigits: 0})"></span>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="destination_account_id" class="block text-sm font-medium text-gray-700">
                                            Landed Into <span class="text-red-600">*</span>
                                        </label>
                                        <select id="destination_account_id" name="destination_account_id"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                required>
                                            <option value="">Select Account</option>
                                            @foreach($destinationAccounts as $account)
                                                <option value="{{ $account->id }}" {{ old('destination_account_id') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->name }} (KES {{ number_format($account->current_balance, 0) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label for="date" class="block text-sm font-medium text-gray-700">
                                            Date <span class="text-red-600">*</span>
                                        </label>
                                        <input type="date" id="date" name="date"
                                               value="{{ old('date', now()->format('Y-m-d')) }}"
                                               max="{{ now()->format('Y-m-d') }}"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                               required>
                                    </div>
                                </div>

                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700">Notes</label>
                                    <input type="text" id="description" name="description"
                                           value="{{ old('description') }}"
                                           placeholder="e.g. M-Pesa ref, or how she described the remittance"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </div>

                                <div class="flex items-center space-x-4">
                                    <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                                        Record Remittance
                                    </button>
                                    <a href="{{ route('referrers.show', $referrer->id) }}"
                                       class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-300 transition">
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
