<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeCredentialsMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $username,
        public string $password,
        public ?string $branchName = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your NAAC account credentials',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.employee-credentials',
        );
    }
}
