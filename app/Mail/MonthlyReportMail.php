<?php

namespace App\Mail;

use App\Models\Account;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Spatie\LaravelPdf\Facades\Pdf;

class MonthlyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Each entry: ['account' => Account, 'statementData' => array, 'period' => string]
     * Populated via withEticaStatements(); empty = no Etica attachments.
     */
    protected array $eticaStatements = [];

    public function __construct(
        public User  $user,
        public array $reportData,
    ) {}

    /**
     * Attach Etica statement data to be rendered as PDF(s) by this Mailable.
     * Replaces the old withAttachments() pattern — callers no longer manage temp files.
     *
     * @param  array{ account: Account, statementData: array, period: string }[]  $statements
     */
    public function withEticaStatements(array $statements): static
    {
        $this->eticaStatements = $statements;
        return $this;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Monthly Financial Report — ' . now()->subMonth()->format('F Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.monthly-report',
            with: [
                'user' => $this->user,
                'data' => $this->reportData,
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->user->emailPreference?->include_pdf) {
            $filename      = 'monthly-report-' . now()->subMonth()->format('Y-m') . '.pdf';
            $attachments[] = Attachment::fromData(
                fn() => $this->generateMonthlyReportPdf(),
                $filename
            )->withMime('application/pdf');
        }

        foreach ($this->eticaStatements as $entry) {
            $account       = $entry['account'];
            $statementData = $entry['statementData'];
            $period        = $entry['period'];
            $filename      = "{$account->name}_Statement_{$period}.pdf";

            $attachments[] = Attachment::fromData(
                fn() => $this->generateEticaPdf($account, $statementData),
                $filename
            )->withMime('application/pdf');
        }

        return $attachments;
    }

    // -------------------------------------------------------------------------

    protected function generateMonthlyReportPdf(): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'monthly_report_') . '.pdf';

        Pdf::view('emails.pdf.monthly-report', [
            'user' => $this->user,
            'data' => $this->reportData,
        ])
            ->format('a4')
            ->save($tempPath);

        $contents = file_get_contents($tempPath);
        @unlink($tempPath);

        return $contents;
    }

    protected function generateEticaPdf(Account $account, array $statementData): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'etica_statement_') . '.pdf';

        Pdf::view('accounts.statement', array_merge($statementData, [
            'account' => $account,
            'user'    => $this->user,
        ]))
            ->format('a4')
            ->save($tempPath);

        $contents = file_get_contents($tempPath);
        @unlink($tempPath);

        return $contents;
    }
}
