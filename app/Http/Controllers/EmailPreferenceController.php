<?php

namespace App\Http\Controllers;

use App\Mail\AnnualReportMail;
use App\Mail\EticaStatementMail;
use App\Mail\MonthlyReportMail;
use App\Models\Account;
use App\Services\ReportDataService;
use App\Services\StatementDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Spatie\LaravelPdf\Facades\Pdf;

class EmailPreferenceController extends Controller
{
    public function __construct(
        private readonly StatementDataService $statementService,
    ) {}

    // =========================================================================
    // SETTINGS PAGE
    // =========================================================================

    public function edit()
    {
        $user = Auth::user();

        $preference = $user->emailPreference()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'monthly_reports' => true,
                'annual_reports'  => true,
                'monthly_day'     => 1,
                'preferred_time'  => '08:00:00',
                'include_pdf'     => true,
                'include_charts'  => false,
            ]
        );

        // Resolve once here so the Blade view never hits the DB itself.
        $eticaAccounts = $user->accounts()
            ->where('type', 'savings')
            ->where('is_active', true)
            ->whereRaw("LOWER(name) LIKE '%etica%'")
            ->get();

        return view('profile.email-preferences', compact('preference', 'eticaAccounts'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'annual_reports'  => 'nullable|in:on',
            'monthly_reports' => 'nullable|in:on',
            'monthly_day'     => 'required|integer|min:1|max:28',
            'preferred_time'  => 'required|date_format:H:i',
            'include_pdf'     => 'nullable|in:on',
            'include_charts'  => 'nullable|in:on',
        ]);

        Auth::user()->emailPreference->update([
            'annual_reports'  => $request->boolean('annual_reports'),
            'monthly_reports' => $request->boolean('monthly_reports'),
            'monthly_day'     => $validated['monthly_day'],
            'preferred_time'  => $validated['preferred_time'],
            'include_pdf'     => $request->boolean('include_pdf'),
            'include_charts'  => $request->boolean('include_charts'),
        ]);

        return redirect()->route('email-preferences.edit')
            ->with('success', 'Email preferences updated successfully!');
    }

    // =========================================================================
    // PDF PREVIEWS (inline browser view)
    // =========================================================================

    public function previewMonthly(ReportDataService $reportService)
    {
        $user = Auth::user();
        $data = $reportService->generateMonthlyReport($user);

        return Pdf::view('emails.pdf.monthly-report', compact('user', 'data'))
            ->format('a4')
            ->inline('monthly-report-preview.pdf');
    }

    public function previewAnnual(ReportDataService $reportService)
    {
        $user = Auth::user();
        $data = $reportService->generateAnnualReport($user);

        return Pdf::view('emails.pdf.annual-report', compact('user', 'data'))
            ->format('a4')
            ->inline('annual-report-preview.pdf');
    }

    public function previewCustom(Request $request, ReportDataService $reportService)
    {
        $user      = Auth::user();
        $startDate = Carbon::parse($request->get('start', now()->startOfMonth()));
        $endDate   = Carbon::parse($request->get('end', now()->endOfMonth()));
        $data      = $reportService->generateCustomReport($user, $startDate, $endDate);

        return Pdf::view('emails.pdf.monthly-report', compact('user', 'data'))
            ->format('a4')
            ->inline('custom-report-preview.pdf');
    }

    // =========================================================================
    // TEST SENDERS
    // =========================================================================

    public function sendTestMonthly(ReportDataService $reportService)
    {
        $user = Auth::user();
        try {
            $reportData = $reportService->generateMonthlyReport($user);
            $from       = now()->subMonth()->startOfMonth();
            $to         = now()->subMonth()->endOfMonth();
            $period     = now()->subMonth()->format('F Y');

            $mailable = (new MonthlyReportMail($user, $reportData))
                ->withEticaStatements($this->buildEticaStatements($user, $from, $to, $period));

            Mail::to($user->email)->send($mailable);

            return redirect()->route('email-preferences.edit')
                ->with('success', 'Test monthly report sent to your email!');

        } catch (\Exception $e) {
            return redirect()->route('email-preferences.edit')
                ->with('error', 'Failed to send test report: ' . $e->getMessage());
        }
    }

    public function sendTestAnnual(ReportDataService $reportService)
    {
        $user = Auth::user();
        try {
            $reportData = $reportService->generateAnnualReport($user);
            $from       = now()->subYear()->startOfYear();
            $to         = now()->subYear()->endOfYear();
            $period     = now()->subYear()->format('Y');

            $mailable = (new AnnualReportMail($user, $reportData))
                ->withEticaStatements($this->buildEticaStatements($user, $from, $to, $period));

            Mail::to($user->email)->send($mailable);

            return redirect()->route('email-preferences.edit')
                ->with('success', 'Test annual report sent to your email!');

        } catch (\Exception $e) {
            return redirect()->route('email-preferences.edit')
                ->with('error', 'Failed to send test report: ' . $e->getMessage());
        }
    }

    public function sendTestEtica()
    {
        $user         = Auth::user();
        $eticaAccount = $this->findEticaAccount($user);

        if (! $eticaAccount) {
            return redirect()->route('email-preferences.edit')
                ->with('error', 'No active Etica account found on your profile.');
        }

        try {
            $from   = now()->subMonth()->startOfMonth();
            $to     = now()->subMonth()->endOfMonth();
            $period = now()->subMonth()->format('F Y');

            $statementData = $this->statementService->buildStatementData($eticaAccount, $from, $to);

            // EticaStatementMail generates its own PDF via attachments() — no temp files here.
            Mail::to($user->email)->send(new EticaStatementMail(
                user:          $user,
                account:       $eticaAccount,
                statementData: $statementData,
                period:        $period,
            ));

            return redirect()->route('email-preferences.edit')
                ->with('success', 'Test Etica statement sent to ' . $user->email . '!');

        } catch (\Exception $e) {
            return redirect()->route('email-preferences.edit')
                ->with('error', 'Failed to send test statement: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // CUSTOM DATE RANGE
    // =========================================================================

    public function sendCustom(Request $request, ReportDataService $reportService)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $user = Auth::user();
        try {
            $startDate  = Carbon::parse($validated['start_date']);
            $endDate    = Carbon::parse($validated['end_date']);
            $reportData = $reportService->generateCustomReport($user, $startDate, $endDate);

            Mail::to($user->email)->send(new MonthlyReportMail($user, $reportData));

            return redirect()->route('email-preferences.edit')
                ->with('success', 'Custom report sent to your email!');

        } catch (\Exception $e) {
            return redirect()->route('email-preferences.edit')
                ->with('error', 'Failed to send custom report: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Return the first active Etica savings account for the given user, or null.
     */
    private function findEticaAccount($user): ?Account
    {
        return $user->accounts()
            ->where('type', 'savings')
            ->where('is_active', true)
            ->whereRaw("LOWER(name) LIKE '%etica%'")
            ->first();
    }

    /**
     * Build the Etica statement data arrays for every active Etica account the user holds.
     * Returns an empty array when the user has no Etica accounts — callers pass this
     * directly to withEticaStatements() without any null check.
     *
     * @param  Carbon  $from
     * @param  Carbon  $to
     * @param  string  $period
     * @return array{ account: Account, statementData: array, period: string }[]
     */
    private function buildEticaStatements($user, Carbon $from, Carbon $to, string $period): array
    {
        return $user->accounts()
            ->where('type', 'savings')
            ->where('is_active', true)
            ->whereRaw("LOWER(name) LIKE '%etica%'")
            ->get()
            ->map(fn(Account $account) => [
                'account'       => $account,
                'statementData' => $this->statementService->buildStatementData($account, $from, $to),
                'period'        => $period,
            ])
            ->all();
    }
}
