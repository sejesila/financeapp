<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

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
            // Generate 4-digit password from user ID (last 4 digits)
            $password = $this->generatePDFPassword();

            // Generate filename with timestamp
            $filename = 'weekly-report-' . now()->format('Y-m-d') . '.pdf';

            // Generate PDF content
            $pdfContent = $this->generatePasswordProtectedPDF($password);

            $attachments[] = Attachment::fromData(fn () => $pdfContent, $filename)
                ->withMime('application/pdf');
        }

        return $attachments;
    }

    /**
     * Generate 4-digit password from user ID
     */
    protected function generatePDFPassword(): string
    {
        // Last 4 digits of padded user ID
        return substr(str_pad($this->user->id, 6, '0', STR_PAD_LEFT), -4);
    }

    /**
     * Generate password-protected PDF using mPDF
     */
    protected function generatePasswordProtectedPDF(string $password): string
    {
        $html = view('emails.pdf.weekly-report', [
            'user' => $this->user,
            'data' => $this->reportData,
        ])->render();

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);

        // Set password protection
        $mpdf->SetProtection(
            ['print', 'copy'],  // Allow printing and copying
            $password,          // User password (required to open)
            null,              // Owner password (optional)
            128                // Encryption strength (128-bit)
        );

        $mpdf->WriteHTML($html);

        // Return PDF as string
        return $mpdf->Output('', 'S');
    }
}
