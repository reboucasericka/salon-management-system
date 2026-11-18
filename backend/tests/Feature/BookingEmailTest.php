<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmationMail;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingEmailService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingEmailTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    private Professional $professional;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-10 08:00:00'));

        $this->admin = User::factory()->admin()->create();

        $this->service = Service::create([
            'name' => 'Corte',
            'description' => 'Corte de cabelo',
            'price' => 25,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $professionalUser = User::create([
            'first_name' => 'Ana',
            'last_name' => 'Profissional',
            'email' => 'ana@salon.test',
            'role' => UserRole::Professional,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->professional = Professional::create([
            'user_id' => $professionalUser->id,
            'name' => 'Ana Profissional',
            'specialty' => 'Cabeleireira',
            'email' => 'ana@salon.test',
            'is_active' => true,
        ]);

        $this->professional->services()->attach($this->service->id);

        foreach ([DayOfWeek::Monday, DayOfWeek::Tuesday] as $day) {
            ProfessionalSchedule::factory()
                ->forProfessional($this->professional)
                ->onDay($day)
                ->window('09:00', '18:00')
                ->create();
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_public_booking_sends_confirmation_email(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/public/appointments', [
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'date' => Carbon::tomorrow()->toDateString(),
            'time' => '10:00',
            'client_name' => 'João Guest',
            'client_email' => 'joao@example.com',
            'client_phone' => '912345678',
        ])->assertCreated();

        Mail::assertSent(BookingConfirmationMail::class, function (BookingConfirmationMail $mail) {
            return $mail->hasTo('joao@example.com');
        });

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_mail_failure_does_not_rollback_appointment(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        Log::shouldReceive('warning')->once();

        $this->postJson('/api/v1/public/appointments', [
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'date' => Carbon::tomorrow()->toDateString(),
            'time' => '11:00',
            'client_name' => 'Maria Guest',
            'client_email' => 'maria@example.com',
        ])->assertCreated();

        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseHas('clients', ['email' => 'maria@example.com']);
    }

    public function test_cancel_sends_email_once(): void
    {
        Mail::fake();

        $client = Client::create([
            'name' => 'Cliente Cancel',
            'email' => 'cancel@example.com',
            'is_active' => true,
        ]);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::tomorrow()->setTime(14, 0),
            'end_time' => Carbon::tomorrow()->setTime(15, 0),
            'status' => Appointment::STATUS_PENDING,
            'total_price' => 25,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/v1/appointments/{$appointment->id}")
            ->assertOk();

        Mail::assertSent(BookingCancelledMail::class, 1);

        $this->deleteJson("/api/v1/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Agendamento já se encontra cancelado.');

        Mail::assertSent(BookingCancelledMail::class, 1);
    }

    public function test_booking_email_service_skips_invalid_email(): void
    {
        Mail::fake();

        $client = Client::create([
            'name' => 'Sem Email',
            'email' => null,
            'is_active' => true,
        ]);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::tomorrow()->setTime(12, 0),
            'end_time' => Carbon::tomorrow()->setTime(13, 0),
            'status' => Appointment::STATUS_PENDING,
            'total_price' => 25,
            'is_active' => true,
        ]);

        $sent = app(BookingEmailService::class)->sendConfirmation($appointment);

        $this->assertFalse($sent);
        Mail::assertNothingSent();
    }
}
