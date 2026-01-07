<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class WeeklyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public array $reportData
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Weekly Financial Report - ' . now()->format('M d, Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-report',
            with: [
                'user' => $this->user,
                'data' => $this->reportData,
            ]
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->user->emailPreference->include_pdf) {
            $pdf = Pdf::loadView('emails.pdf.weekly-report', [
                'user' => $this->user,
                'data' => $this->reportData,
            ]);

            // Generate filename with timestamp
            $filename = 'weekly-report-' . now()->format('M-d-Y') . '.pdf';
            // Result: weekly-report-Jan-07-2026.pdf

            $attachments[] = Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
