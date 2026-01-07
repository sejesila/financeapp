<?php

namespace App\Http\Controllers;

use App\Services\ReportDataService;
use App\Mail\WeeklyReportMail;
use App\Mail\MonthlyReportMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class EmailPreferenceController extends Controller
{
    public function edit()
    {
        $user = Auth::user();
        $preference = $user->emailPreference;

        return view('profile.email-preferences', compact('preference'));
    }

    public function update(Request $request)
    {

        $validated = $request->validate([
            'weekly_reports' => 'nullable|in:on',
            'monthly_reports' => 'nullable|in:on',
            'weekly_day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'monthly_day' => 'required|integer|min:1|max:28',
            'preferred_time' => 'required|date_format:H:i',
            'include_pdf' => 'nullable|in:on',
            'include_charts' => 'nullable|in:on',
        ]);

        $user = Auth::user();


        // Use the validated data directly, with defaults for checkboxes
      $user->emailPreference->update([
            'weekly_reports' => $request->boolean('weekly_reports'),
            'monthly_reports' => $request->boolean('monthly_reports'),
            'weekly_day' => $validated['weekly_day'],
            'monthly_day' => $validated['monthly_day'],
            'preferred_time' => $validated['preferred_time'],
            'include_pdf' => $request->boolean('include_pdf'),
            'include_charts' => $request->boolean('include_charts'),
        ]);

        return redirect()->route('email-preferences.edit')
            ->with('success', 'Email preferences updated successfully!');
    }

    public function sendTestWeekly(ReportDataService $reportService)
    {
        $user = Auth::user();

        try {
            $reportData = $reportService->generateWeeklyReport($user);
            Mail::to($user->email)->send(new WeeklyReportMail($user, $reportData));

            return redirect()->route('email-preferences.edit')
                ->with('success', 'Test weekly report sent to your email!');
        } catch (\Exception $e) {
            return redirect()->route('email-preferences.edit')
                ->with('error', 'Failed to send test report: ' . $e->getMessage());
        }
    }

    public function sendTestMonthly(ReportDataService $reportService)
    {
        $user = Auth::user();

        try {
            $reportData = $reportService->generateMonthlyReport($user);
            Mail::to($user->email)->send(new MonthlyReportMail($user, $reportData));

            return redirect()->route('email-preferences.edit')
                ->with('success', 'Test monthly report sent to your email!');
        } catch (\Exception $e) {
            return redirect()->route('email-preferences.edit')
                ->with('error', 'Failed to send test report: ' . $e->getMessage());
        }
    }

    public function sendCustom(Request $request, ReportDataService $reportService)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $user = Auth::user();

        try {
            $startDate = \Carbon\Carbon::parse($validated['start_date']);
            $endDate = \Carbon\Carbon::parse($validated['end_date']);

            $reportData = $reportService->generateCustomReport($user, $startDate, $endDate);
            Mail::to($user->email)->send(new MonthlyReportMail($user, $reportData));

            return redirect()->route('email-preferences.edit')
                ->with('success', 'Custom report sent to your email!');
        } catch (\Exception $e) {
            return redirect()->route('email-preferences.edit')
                ->with('error', 'Failed to send custom report: ' . $e->getMessage());
        }
    }
}
