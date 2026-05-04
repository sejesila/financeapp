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

    public function __construct(
        public User  $user,
        public array $reportData
    ) {}

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
        if (!$this->user->emailPreference?->include_pdf) {
            return [];
        }

        $filename = 'monthly-report-' . now()->format('Y-m') . '.pdf';

        return [
            Attachment::fromData(fn () => $this->generatePdf(), $filename)
                ->withMime('application/pdf'),
        ];
    }

    protected function generatePdf(): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'monthly_report_') . '.pdf';

        Pdf::view('emails.pdf.monthly-report', [
            'user' => $this->user,
            'data' => $this->reportData,
        ])
            ->format('a4')
            ->save($tempPath);

        $contents = file_get_contents($tempPath);
        unlink($tempPath);

        return $contents;
    }

}
