<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResetPasswordCodeMail extends Mailable
{


    public function __construct(
        public string $code,
        public string $userName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Mã xác thực đặt lại mật khẩu Fluid Notes',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password-code',
        );
    }
}
