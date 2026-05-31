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

class AnnualReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var Attachment[] Extra PDFs bolted on externally */
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
                'user' => $this->user,
                'data' => $this->reportData,
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
                fn() => $this->generatePdf(),
                $filename
            )->withMime('application/pdf');
        }

        return array_merge($attachments, $this->extraAttachments);
    }

    protected function generatePdf(): string
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
}
