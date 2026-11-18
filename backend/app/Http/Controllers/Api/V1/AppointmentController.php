<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAppointmentRequest;
use App\Http\Requests\Api\V1\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\AppointmentGoogleSyncService;
use App\Services\AppointmentService;
use App\Services\BookingEmailService;
use App\Services\ProfessionalService;
use App\Services\ProfessionalServiceAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly ProfessionalService $professionalService,
        private readonly ProfessionalServiceAssignmentService $assignmentService,
        private readonly BookingEmailService $bookingEmailService,
        private readonly AppointmentGoogleSyncService $googleSync,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Appointment::class);

        $query = Appointment::query()
            ->with(['client', 'service', 'professional'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('professional_id'), fn ($q) => $q->where('professional_id', $request->integer('professional_id')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('start_time', $request->string('date')))
            ->orderBy('start_time');

        $user = $request->user();

        if ($user->role === UserRole::Professional) {
            $professionalId = $user->professionalProfile?->id;
            $query->where('professional_id', $professionalId);
        }

        if ($user->role === UserRole::Client) {
            $clientId = $user->clientProfile?->id;
            $query->where('client_id', $clientId);
        }

        $appointments = $query->paginate($request->integer('per_page', 15));

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $this->authorize('create', Appointment::class);

        $service = Service::findOrFail($request->integer('service_id'));
        $professional = $this->professionalService->ensureIsActive($request->integer('professional_id'));
        $this->assignmentService->ensureOffersService($professional, $service->id);
        $startTime = Carbon::parse($request->string('start_time')->toString());
        $endTime = $this->appointmentService->calculateEndTime($startTime, $service);

        $this->appointmentService->ensureNoScheduleConflict(
            $request->integer('professional_id'),
            $startTime,
            $endTime
        );

        $this->appointmentService->ensureWithinProfessionalSchedule(
            $request->integer('professional_id'),
            $startTime,
            $endTime
        );

        $appointment = Appointment::create([
            ...$request->validated(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $request->input('status', Appointment::STATUS_PENDING),
            'total_price' => $service->price,
        ]);

        $appointment->load(['client', 'service', 'professional']);
        $this->bookingEmailService->sendConfirmation($appointment);
        $this->googleSync->sync($appointment->fresh(['client', 'service', 'professional']));
        $appointment = $appointment->fresh()->load(['client', 'service', 'professional']);

        return response()->json([
            'message' => 'Agendamento criado com sucesso.',
            'data' => new AppointmentResource($appointment),
        ], 201);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);

        return response()->json([
            'data' => new AppointmentResource($appointment->load(['client', 'service', 'professional', 'reminders'])),
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        $data = $request->validated();
        $service = isset($data['service_id'])
            ? Service::findOrFail($data['service_id'])
            : $appointment->service;

        $startTime = isset($data['start_time'])
            ? Carbon::parse($data['start_time'])
            : $appointment->start_time;

        $endTime = $this->appointmentService->calculateEndTime($startTime, $service);
        $professionalId = $data['professional_id'] ?? $appointment->professional_id;

        $professional = $this->professionalService->ensureIsActive((int) $professionalId);
        $this->assignmentService->ensureOffersService($professional, $service->id);

        $this->appointmentService->ensureNoScheduleConflict(
            $professionalId,
            $startTime,
            $endTime,
            $appointment->id
        );

        $this->appointmentService->ensureWithinProfessionalSchedule(
            $professionalId,
            $startTime,
            $endTime
        );

        $wasCanceled = $appointment->status === Appointment::STATUS_CANCELED;

        $appointment->update([
            ...$data,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_price' => $service->price,
        ]);

        $appointment = $appointment->fresh()->load(['client', 'service', 'professional']);

        if (
            ! $wasCanceled
            && $appointment->status === Appointment::STATUS_CANCELED
        ) {
            $this->bookingEmailService->sendCancellation($appointment);
            $this->googleSync->remove($appointment);
        } else {
            $this->googleSync->sync($appointment);
        }

        $appointment = $appointment->fresh()->load(['client', 'service', 'professional']);

        return response()->json([
            'message' => 'Agendamento atualizado com sucesso.',
            'data' => new AppointmentResource($appointment),
        ]);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $this->authorize('delete', $appointment);

        if ($appointment->status === Appointment::STATUS_CANCELED) {
            return response()->json([
                'message' => 'Agendamento já se encontra cancelado.',
            ]);
        }

        $appointment->update([
            'status' => Appointment::STATUS_CANCELED,
            'is_active' => false,
        ]);

        $appointment->load(['client', 'service', 'professional']);
        $this->bookingEmailService->sendCancellation($appointment);
        $this->googleSync->remove($appointment->fresh(['client', 'service', 'professional']));

        return response()->json([
            'message' => 'Agendamento cancelado com sucesso.',
        ]);
    }
}
