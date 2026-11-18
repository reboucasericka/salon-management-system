<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envio síncrono nesta fase. Pode passar a implementar ShouldQueue depois
 * quando o worker de queue estiver operacional em produção.
 */
class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmação de agendamento — '.app(\App\Services\SettingsService::class)->salonName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking-confirmation',
            with: [
                'appointment' => $this->appointment,
                'salonName' => app(\App\Services\SettingsService::class)->salonName(),
                'timezone' => app(\App\Services\SettingsService::class)->timezone(),
            ],
        );
    }
}
