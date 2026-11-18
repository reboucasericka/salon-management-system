<?php

namespace Tests\Feature;

use App\Enums\ReminderType;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Client;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppointmentReminderTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    private Professional $professional;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00', 'UTC'));

        $this->service = Service::create([
            'name' => 'Corte',
            'description' => 'Corte de cabelo',
            'price' => 25,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $proUser = User::factory()->professional()->create();
        $this->professional = Professional::create([
            'user_id' => $proUser->id,
            'name' => 'Ana Profissional',
            'specialty' => 'Cabeleireira',
            'email' => 'ana@salon.test',
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'name' => 'Maria Cliente',
            'email' => 'maria@example.com',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeAppointment(Carbon $start, array $overrides = []): Appointment
    {
        return Appointment::create(array_merge([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
            'total_price' => 25,
            'is_active' => true,
            'reminder_sent' => false,
        ], $overrides));
    }

    public function test_sends_24h_reminder_once(): void
    {
        Mail::fake();

        $appointment = $this->makeAppointment(now()->addHours(24)->addMinutes(30));

        Artisan::call('appointments:send-reminders');
        Artisan::call('appointments:send-reminders');

        Mail::assertSent(AppointmentReminderMail::class, function (AppointmentReminderMail $mail) use ($appointment) {
            return $mail->appointment->is($appointment)
                && $mail->reminderType === ReminderType::TwentyFourHours
                && $mail->hasTo('maria@example.com');
        });

        Mail::assertSent(AppointmentReminderMail::class, 1);

        $this->assertDatabaseHas('appointment_reminders', [
            'appointment_id' => $appointment->id,
            'type' => ReminderType::TwentyFourHours->value,
        ]);
        $this->assertNotNull(
            AppointmentReminder::query()
                ->where('appointment_id', $appointment->id)
                ->where('type', ReminderType::TwentyFourHours)
                ->value('sent_at')
        );
        $this->assertTrue($appointment->fresh()->reminder_sent);
    }

    public function test_24h_sent_does_not_block_2h(): void
    {
        Mail::fake();

        $start = now()->addHours(24)->addMinutes(30);
        $appointment = $this->makeAppointment($start);

        Artisan::call('appointments:send-reminders');
        Mail::assertSent(AppointmentReminderMail::class, 1);

        Carbon::setTestNow($start->copy()->subHours(2)->subMinutes(30));

        Artisan::call('appointments:send-reminders');

        Mail::assertSent(AppointmentReminderMail::class, 2);
        Mail::assertSent(AppointmentReminderMail::class, fn (AppointmentReminderMail $mail) => $mail->reminderType === ReminderType::TwoHours);

        $this->assertDatabaseCount('appointment_reminders', 2);
        $this->assertEquals(2, AppointmentReminder::query()->whereNotNull('sent_at')->count());
    }

    public function test_canceled_and_completed_do_not_receive(): void
    {
        Mail::fake();

        $this->makeAppointment(now()->addHours(24)->addMinutes(20), [
            'status' => Appointment::STATUS_CANCELED,
        ]);
        $this->makeAppointment(now()->addHours(24)->addMinutes(40), [
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        $summary = Artisan::call('appointments:send-reminders');
        $this->assertSame(0, $summary);

        Mail::assertNothingSent();
    }

    public function test_outside_window_does_not_receive(): void
    {
        Mail::fake();

        $this->makeAppointment(now()->addHours(48));

        Artisan::call('appointments:send-reminders');

        Mail::assertNothingSent();
    }

    public function test_client_without_email_is_skipped(): void
    {
        Mail::fake();

        $client = Client::create([
            'name' => 'Sem Email',
            'email' => null,
            'is_active' => true,
        ]);

        $this->makeAppointment(now()->addHours(24)->addMinutes(15), [
            'client_id' => $client->id,
        ]);

        Artisan::call('appointments:send-reminders');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('appointment_reminders', 0);
    }

    public function test_mail_failure_does_not_mark_sent(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        $appointment = $this->makeAppointment(now()->addHours(2)->addMinutes(30));

        Artisan::call('appointments:send-reminders');

        $reminder = AppointmentReminder::query()
            ->where('appointment_id', $appointment->id)
            ->where('type', ReminderType::TwoHours)
            ->first();

        $this->assertNotNull($reminder);
        $this->assertNull($reminder->sent_at);
        $this->assertSame(1, $reminder->attempts);
        $this->assertFalse($appointment->fresh()->reminder_sent);
    }

    public function test_pending_status_is_eligible(): void
    {
        Mail::fake();

        $this->makeAppointment(now()->addHours(2)->addMinutes(15), [
            'status' => Appointment::STATUS_PENDING,
        ]);

        Artisan::call('appointments:send-reminders');

        Mail::assertSent(AppointmentReminderMail::class, 1);
    }

    public function test_command_summary_exit_code(): void
    {
        Mail::fake();

        $this->makeAppointment(now()->addHours(24)->addMinutes(10));

        $exit = Artisan::call('appointments:send-reminders');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Enviados', Artisan::output());
    }

    public function test_timezone_window_uses_app_timezone(): void
    {
        Mail::fake();

        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00', 'UTC'));

        $inWindow = $this->makeAppointment(Carbon::parse('2026-08-11 10:30:00', 'UTC'));
        $this->makeAppointment(Carbon::parse('2026-08-12 10:30:00', 'UTC'));

        Artisan::call('appointments:send-reminders');

        Mail::assertSent(AppointmentReminderMail::class, 1);
        $this->assertNotNull(
            AppointmentReminder::query()
                ->where('appointment_id', $inWindow->id)
                ->whereNotNull('sent_at')
                ->first()
        );
    }
}
