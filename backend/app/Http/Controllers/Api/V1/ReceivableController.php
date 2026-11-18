<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReceivableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MarkReceivableReceivedRequest;
use App\Http\Requests\Api\V1\StoreReceivableRequest;
use App\Http\Requests\Api\V1\UpdateReceivableRequest;
use App\Http\Resources\ReceivableResource;
use App\Models\Receivable;
use App\Services\ReceivableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ReceivableController extends Controller
{
    public function __construct(private readonly ReceivableService $receivableService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Receivable::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'received', 'overdue'])],
            'reference' => ['nullable', 'string', Rule::in(['service', 'sale', 'none'])],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'sale_id' => ['nullable', 'integer', 'exists:sales,id'],
            'professional_id' => ['nullable', 'integer', 'exists:professionals,id'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $receivables = Receivable::query()
            ->with(['client', 'professional', 'service', 'sale', 'creator', 'receiver'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('description', 'like', "%{$search}%");
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = $request->string('status')->toString();

                if ($status === 'overdue') {
                    $query->where('status', ReceivableStatus::Pending)
                        ->where('created_at', '<', now()->subDays(Receivable::OVERDUE_AFTER_DAYS));

                    return;
                }

                $query->where('status', $status);
            })
            ->when($request->filled('reference'), function ($query) use ($request) {
                match ($request->string('reference')->toString()) {
                    'service' => $query->whereNotNull('service_id'),
                    'sale' => $query->whereNotNull('sale_id'),
                    'none' => $query->whereNull('service_id')->whereNull('sale_id'),
                    default => null,
                };
            })
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->integer('service_id')))
            ->when($request->filled('sale_id'), fn ($q) => $q->where('sale_id', $request->integer('sale_id')))
            ->when($request->filled('professional_id'), fn ($q) => $q->where('professional_id', $request->integer('professional_id')))
            ->when($request->filled('created_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('created_from')))
            ->when($request->filled('created_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('created_to')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return ReceivableResource::collection($receivables);
    }

    public function store(StoreReceivableRequest $request): JsonResponse
    {
        $this->authorize('create', Receivable::class);

        $receivable = $this->receivableService->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'Conta a receber criada com sucesso.',
            'data' => new ReceivableResource(
                $receivable->load(['client', 'professional', 'service', 'sale', 'creator', 'receiver'])
            ),
        ], 201);
    }

    public function show(Receivable $receivable): JsonResponse
    {
        $this->authorize('view', $receivable);

        return response()->json([
            'data' => new ReceivableResource(
                $receivable->load(['client', 'professional', 'service', 'sale', 'creator', 'receiver'])
            ),
        ]);
    }

    public function update(UpdateReceivableRequest $request, Receivable $receivable): JsonResponse
    {
        $this->authorize('update', $receivable);

        $receivable = $this->receivableService->update($receivable, $request->validated());

        return response()->json([
            'message' => 'Conta a receber atualizada com sucesso.',
            'data' => new ReceivableResource($receivable),
        ]);
    }

    public function destroy(Receivable $receivable): Response
    {
        $this->authorize('delete', $receivable);

        $this->receivableService->delete($receivable);

        return response()->noContent();
    }

    public function receive(MarkReceivableReceivedRequest $request, Receivable $receivable): JsonResponse
    {
        $this->authorize('markAsReceived', $receivable);

        $receivable = $this->receivableService->markAsReceived(
            $receivable,
            $request->user(),
            $request->validated('received_at')
        );

        return response()->json([
            'message' => 'Conta a receber marcada como recebida.',
            'data' => new ReceivableResource($receivable),
        ]);
    }
}
