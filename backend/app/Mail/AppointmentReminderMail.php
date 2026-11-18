<?php

namespace App\Mail;

use App\Enums\ReminderType;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envio síncrono nesta fase. Pode passar a ShouldQueue quando o worker estiver operacional.
 */
class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public ReminderType $reminderType,
    ) {}

    public function envelope(): Envelope
    {
        $salonName = app(\App\Services\SettingsService::class)->salonName();

        return new Envelope(
            subject: 'Lembrete do seu agendamento — '.$salonName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.appointment-reminder',
            with: [
                'appointment' => $this->appointment,
                'reminderType' => $this->reminderType,
                'hoursText' => $this->reminderType->label(),
                'salonName' => app(\App\Services\SettingsService::class)->salonName(),
                'timezone' => app(\App\Services\SettingsService::class)->timezone(),
            ],
        );
    }
}
