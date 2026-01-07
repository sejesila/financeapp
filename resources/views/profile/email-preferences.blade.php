<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Email Report Preferences') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Validation Errors -->
            @if ($errors->any())
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium">There were some errors with your submission:</h3>
                            <ul class="mt-2 text-sm list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Success/Error Messages -->
            @if(session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            <!-- Main Settings Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center mb-6">
                        <svg class="w-8 h-8 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Email Report Settings</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Configure your automated financial report preferences</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('email-preferences.update') }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <!-- Report Types -->
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                            <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-4">Report Types</h4>

                            <div class="space-y-4">
                                <!-- Weekly Reports -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input
                                            id="weekly_reports"
                                            name="weekly_reports"
                                            type="checkbox"
                                            {{ old('weekly_reports', $preference->weekly_reports) ? 'checked' : '' }}
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                        >
                                    </div>
                                    <div class="ml-3">
                                        <label for="weekly_reports" class="font-medium text-gray-900 dark:text-white">
                                            Weekly Reports
                                        </label>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Receive a summary of your weekly transactions, spending, and account balances
                                        </p>
                                    </div>
                                </div>

                                <!-- Monthly Reports -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input
                                            id="monthly_reports"
                                            name="monthly_reports"
                                            type="checkbox"
                                            {{ old('monthly_reports', $preference->monthly_reports) ? 'checked' : '' }}
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                        >
                                    </div>
                                    <div class="ml-3">
                                        <label for="monthly_reports" class="font-medium text-gray-900 dark:text-white">
                                            Monthly Reports
                                        </label>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Receive a comprehensive monthly overview with budget analysis and insights
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Schedule Settings -->
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                            <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-4">Schedule</h4>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Weekly Day -->
                                <div>
                                    <label for="weekly_day" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Weekly Report Day
                                    </label>
                                    <select
                                        id="weekly_day"
                                        name="weekly_day"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('weekly_day') border-red-500 @enderror"
                                    >
                                        <option value="monday" {{ old('weekly_day', $preference->weekly_day) === 'monday' ? 'selected' : '' }}>Monday</option>
                                        <option value="tuesday" {{ old('weekly_day', $preference->weekly_day) === 'tuesday' ? 'selected' : '' }}>Tuesday</option>
                                        <option value="wednesday" {{ old('weekly_day', $preference->weekly_day) === 'wednesday' ? 'selected' : '' }}>Wednesday</option>
                                        <option value="thursday" {{ old('weekly_day', $preference->weekly_day) === 'thursday' ? 'selected' : '' }}>Thursday</option>
                                        <option value="friday" {{ old('weekly_day', $preference->weekly_day) === 'friday' ? 'selected' : '' }}>Friday</option>
                                        <option value="saturday" {{ old('weekly_day', $preference->weekly_day) === 'saturday' ? 'selected' : '' }}>Saturday</option>
                                        <option value="sunday" {{ old('weekly_day', $preference->weekly_day) === 'sunday' ? 'selected' : '' }}>Sunday</option>
                                    </select>
                                    @error('weekly_day')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                    @else
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Day to receive weekly reports</p>
                                        @enderror
                                </div>

                                <!-- Monthly Day -->
                                <div>
                                    <label for="monthly_day" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Monthly Report Day
                                    </label>
                                    <select
                                        id="monthly_day"
                                        name="monthly_day"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('monthly_day') border-red-500 @enderror"
                                    >
                                        @for($day = 1; $day <= 28; $day++)
                                            <option value="{{ $day }}" {{ old('monthly_day', $preference->monthly_day) == $day ? 'selected' : '' }}>
                                                Day {{ $day }} of the month
                                            </option>
                                        @endfor
                                    </select>
                                    @error('monthly_day')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                    @else
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Day of the month (1-28 only to avoid issues with short months)</p>
                                        @enderror
                                </div>

                                <!-- Preferred Time -->
                                <div class="md:col-span-2">
                                    <label for="preferred_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Preferred Time
                                    </label>
                                    <input
                                        type="time"
                                        id="preferred_time"
                                        name="preferred_time"
                                        value="{{ old('preferred_time', $preference->preferred_time ? \Carbon\Carbon::parse($preference->preferred_time)->format('H:i') : '08:00') }}"
                                        class="w-full md:w-64 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('preferred_time') border-red-500 @enderror"
                                    >
                                    @error('preferred_time')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                    @else
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Time of day to receive reports (24-hour format)</p>
                                        @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Report Content Options -->
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                            <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-4">Report Content</h4>

                            <div class="space-y-4">
                                <!-- Include PDF -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input
                                            id="include_pdf"
                                            name="include_pdf"
                                            type="checkbox"
                                            {{ old('include_pdf', $preference->include_pdf) ? 'checked' : '' }}
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                        >
                                    </div>
                                    <div class="ml-3">
                                        <label for="include_pdf" class="font-medium text-gray-900 dark:text-white">
                                            Include PDF Attachment
                                        </label>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Attach a downloadable PDF version of your report
                                        </p>
                                    </div>
                                </div>

                                <!-- Include Charts -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input
                                            id="include_charts"
                                            name="include_charts"
                                            type="checkbox"
                                            {{ old('include_charts', $preference->include_charts) ? 'checked' : '' }}
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                        >
                                    </div>
                                    <div class="ml-3">
                                        <label for="include_charts" class="font-medium text-gray-900 dark:text-white">
                                            Include Charts & Graphs
                                        </label>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Add visual spending charts and trend graphs (coming soon)
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Last Sent Info -->
                        @if($preference->last_weekly_sent || $preference->last_monthly_sent)
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h5 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Last Report Sent</h5>
                                <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                    @if($preference->last_weekly_sent)
                                        <p>📅 Weekly: {{ $preference->last_weekly_sent->format('M d, Y \a\t h:i A') }}</p>
                                    @endif
                                    @if($preference->last_monthly_sent)
                                        <p>📅 Monthly: {{ $preference->last_monthly_sent->format('M d, Y \a\t h:i A') }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Save Button -->
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('profile.edit') }}" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                                Cancel
                            </a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg transition">
                                Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Test & Custom Reports Card -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Test Reports -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <svg class="w-6 h-6 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Test Reports</h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Send a test report to preview what you'll receive
                        </p>

                        <div class="space-y-3">
                            <form method="POST" action="{{ route('email-preferences.test-weekly') }}">
                                @csrf
                                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium px-4 py-2 rounded-lg transition flex items-center justify-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    Send Test Weekly Report
                                </button>
                            </form>

                            <form method="POST" action="{{ route('email-preferences.test-monthly') }}">
                                @csrf
                                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium px-4 py-2 rounded-lg transition flex items-center justify-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    Send Test Monthly Report
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Custom Report -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <svg class="w-6 h-6 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Custom Date Range</h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Generate a report for any date range
                        </p>

                        <form method="POST" action="{{ route('email-preferences.send-custom') }}" class="space-y-3">
                            @csrf
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Start Date
                                </label>
                                <input
                                    type="date"
                                    id="start_date"
                                    name="start_date"
                                    value="{{ old('start_date') }}"
                                    required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('start_date') border-red-500 @enderror"
                                >
                                @error('start_date')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    End Date
                                </label>
                                <input
                                    type="date"
                                    id="end_date"
                                    name="end_date"
                                    value="{{ old('end_date') }}"
                                    required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('end_date') border-red-500 @enderror"
                                >
                                @error('end_date')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg transition flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                Send Custom Report
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Info Card -->
            <div class="bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-400 p-4 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">About Email Reports</h3>
                        <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                            <ul class="list-disc list-inside space-y-1">
                                <li>Reports are sent automatically based on your schedule</li>
                                <li>All reports include transaction summaries, account balances, and insights</li>
                                <li>Monthly reports also include budget performance analysis</li>
                                <li>PDF attachments are great for keeping offline records</li>
                                <li>You can send test reports to preview the content anytime</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
