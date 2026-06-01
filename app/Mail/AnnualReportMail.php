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

class AnnualReportMail extends Mailable
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
     * Callers pass data only — this class owns the PDF generation and temp-file lifecycle.
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
        $year = now()->subYear()->format('Y');

        return new Envelope(
            subject: "Your Annual Financial Report — {$year}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.annual-report',
            with: [
                'user'     => $this->user,
                'data'     => $this->reportData,
                'hasEtica' => ! empty($this->eticaStatements),
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->user->emailPreference?->include_pdf) {
            $year          = now()->subYear()->format('Y');
            $filename      = "annual-report-{$year}.pdf";
            $attachments[] = Attachment::fromData(
                fn() => $this->generateAnnualReportPdf(),
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

    protected function generateAnnualReportPdf(): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'annual_report_') . '.pdf';

        Pdf::view('emails.pdf.annual-report', [
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
