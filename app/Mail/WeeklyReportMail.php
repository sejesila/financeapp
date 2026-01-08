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
            $password = $this->generatePDFPassword();
            $filename = 'weekly-report-' . now()->format('Y-m-d') . '.pdf';
            $pdfContent = $this->generatePasswordProtectedPDF($password);

            $attachments[] = Attachment::fromData(fn () => $pdfContent, $filename)
                ->withMime('application/pdf');
        }

        return $attachments;
    }

    protected function generatePDFPassword(): string
    {
        return substr(str_pad($this->user->id, 6, '0', STR_PAD_LEFT), -4);
    }

    protected function generatePasswordProtectedPDF(string $password): string
    {
        // Ensure temp directory exists
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

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
            'tempDir' => $tempDir,  // Use storage temp directory
        ]);

        $mpdf->SetProtection(['print', 'copy'], $password, null, 128);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
