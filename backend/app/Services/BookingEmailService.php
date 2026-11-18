<?php

namespace App\Services;

use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmationMail;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails de agendamento (paridade com legado: destinatário = cliente).
 * Falhas SMTP são logadas e NÃO revertem o appointment.
 */
class BookingEmailService
{
    public function sendConfirmation(Appointment $appointment): bool
    {
        return $this->send($appointment, new BookingConfirmationMail($appointment), 'confirmation');
    }

    public function sendCancellation(Appointment $appointment): bool
    {
        return $this->send($appointment, new BookingCancelledMail($appointment), 'cancellation');
    }

    private function send(Appointment $appointment, object $mailable, string $type): bool
    {
        $appointment->loadMissing(['client', 'service', 'professional']);

        $email = trim((string) ($appointment->client?->email ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info('Booking email skipped: client without valid email.', [
                'type' => $type,
                'appointment_id' => $appointment->id,
            ]);

            return false;
        }

        try {
            Mail::to($email)->send($mailable);

            return true;
        } catch (Throwable $e) {
            Log::warning('Booking email failed; appointment kept.', [
                'type' => $type,
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
