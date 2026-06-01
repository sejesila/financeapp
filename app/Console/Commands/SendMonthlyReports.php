<?php

namespace App\Console\Commands;

use App\Mail\MonthlyReportMail;
use App\Models\User;
use App\Services\ReportDataService;
use App\Services\StatementDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;
use Spatie\LaravelPdf\Facades\Pdf;

class SendMonthlyReports extends Command
{
    protected $signature = 'reports:send-monthly
                            {--force  : Force send regardless of schedule}
                            {--month= : Month to generate for (YYYY-MM), defaults to last month}
                            {--user=  : Only send to this user ID}';

    protected $description = 'Send monthly financial reports to all users; attaches Etica statement PDF for users who have an Etica savings account';

    public function handle(ReportDataService $reportService, StatementDataService $statementService): int
    {
        $this->info('Starting monthly report generation...');

        $currentDay = now()->day;

        $targetMonth = $this->option('month')
            ? Carbon::parse($this->option('month') . '-01')->startOfMonth()
            : now()->subMonth()->startOfMonth();

        $from   = $targetMonth->copy()->startOfMonth();
        $to     = $targetMonth->copy()->endOfMonth();
        $period = $targetMonth->format('F Y');

        $this->info("Period: {$from->toDateString()} → {$to->toDateString()}");

        $query = User::whereHas('emailPreference', function ($q) use ($currentDay) {
            $q->where('monthly_reports', true)
                ->where('monthly_day', $currentDay);

            if (! $this->option('force')) {
                $q->where(function ($inner) {
                    $inner->whereNull('last_monthly_sent')
                        ->orWhere('last_monthly_sent', '<', now()->subDays(25));
                });
            }
        })
            ->with([
                'emailPreference',
                // Eager-load Etica accounts so we can conditionally attach the statement.
                // Users without one simply get no attachment — no separate command needed.
                'accounts' => fn($q) => $q->where('type', 'savings')
                    ->where('is_active', true)
                    ->whereRaw("LOWER(name) LIKE '%etica%'"),
            ]);

        if ($this->option('user')) {
            $query->where('id', $this->option('user'));
        }

        $users = $query->get();
        $this->info("Found {$users->count()} user(s) to send reports to.");

        $successCount = 0;
        $failCount    = 0;

        foreach ($users as $user) {
            $tmpFiles = [];

            try {
                $this->info("  Processing {$user->name} ({$user->email})...");

                // ── 1. Monthly report PDF ─────────────────────────────────────
                $reportData = $reportService->generateMonthlyReport($user);
                $reportPath = sys_get_temp_dir()
                    . "/monthly_report_{$user->id}_{$targetMonth->format('Y_m')}.pdf";

                Pdf::view('emails.pdf.monthly-report', [
                    'user' => $user,
                    'data' => $reportData,
                ])
                    ->format('a4')
                    ->save($reportPath);

                $tmpFiles[] = $reportPath;

                // ── 2. Etica statement PDFs (only when the user has one) ───────
                $statementAttachments = [];

                foreach ($user->accounts as $account) {
                    $statementData = $statementService->buildStatementData($account, $from, $to);
                    $statementPath = sys_get_temp_dir()
                        . "/etica_statement_{$user->id}_{$account->id}_{$targetMonth->format('Y_m')}.pdf";

                    Pdf::view('accounts.statement', array_merge($statementData, [
                        'account' => $account,
                        'user'    => $user,
                    ]))
                        ->format('a4')
                        ->save($statementPath);

                    $tmpFiles[]             = $statementPath;
                    $statementAttachments[] = [
                        'path'     => $statementPath,
                        'filename' => "{$account->name}_Statement_{$period}.pdf",
                    ];

                    $this->info("    Statement ready for {$account->name}");
                }

                // ── 3. Send one email (report + optional statement attachments) ─
                $mail = new MonthlyReportMail($user, $reportData);

                if (! empty($statementAttachments)) {
                    $mail->withAttachments(
                        collect($statementAttachments)
                            ->map(fn($s) => Attachment::fromPath($s['path'])
                                ->as($s['filename'])
                                ->withMime('application/pdf'))
                            ->all()
                    );
                }

                Mail::to($user->email)->send($mail);

                $user->emailPreference->update(['last_monthly_sent' => now()]);

                $label = empty($statementAttachments) ? '' : ' + Etica statement';
                $this->info("    ✓ Report{$label} sent to {$user->email}");
                $successCount++;

            } catch (\Throwable $e) {
                $this->error("    ✗ Failed for {$user->email}: {$e->getMessage()}");
                $failCount++;
            } finally {
                foreach ($tmpFiles as $path) {
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Successfully sent: {$successCount}");
        if ($failCount > 0) {
            $this->error("Failed: {$failCount}");
        }
        $this->info("Total processed: " . ($successCount + $failCount));

        return Command::SUCCESS;
    }
}
