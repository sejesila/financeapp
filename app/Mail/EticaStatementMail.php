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

class EticaStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User    $user,
        public readonly Account $account,
        public readonly array   $statementData,  // same shape StatementController uses
        public readonly string  $period,         // e.g. "May 2026"
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->account->name} Statement – {$this->period}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.etica-statement',
            with: [
                'user'    => $this->user,
                'account' => $this->account,
                'period'  => $this->period,
                'data'    => $this->statementData,
            ],
        );
    }

    public function attachments(): array
    {
        $filename = "{$this->account->name}_Statement_{$this->period}.pdf";

        return [
            Attachment::fromData(
                fn() => $this->generateStatementPdf(),
                $filename
            )->withMime('application/pdf'),
        ];
    }

    protected function generateStatementPdf(): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'etica_statement_') . '.pdf';

        Pdf::view('accounts.statement', array_merge($this->statementData, [
            'account' => $this->account,
            'user'    => $this->user,
        ]))
            ->format('a4')
            ->save($tempPath);

        $contents = file_get_contents($tempPath);
        @unlink($tempPath);

        return $contents;
    }
}
