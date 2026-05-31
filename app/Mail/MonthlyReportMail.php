<?php

namespace App\Mail;

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

    /** @var Attachment[] Extra PDFs bolted on by SendMonthlyReportsWithStatement */
    protected array $extraAttachments = [];

    public function __construct(
        public User  $user,
        public array $reportData,
    ) {}

    /**
     * Fluently attach extra PDFs without changing existing callers.
     *
     * @param  Attachment[]  $attachments
     */
    public function withAttachments(array $attachments): static
    {
        $this->extraAttachments = array_merge($this->extraAttachments, $attachments);
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

        return array_merge($attachments, $this->extraAttachments);
    }

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
}
