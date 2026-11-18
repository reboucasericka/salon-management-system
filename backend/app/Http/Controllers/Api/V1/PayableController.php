<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PayableStatus;
use App\Enums\PayableType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MarkPayablePaidRequest;
use App\Http\Requests\Api\V1\StorePayableRequest;
use App\Http\Requests\Api\V1\UpdatePayableRequest;
use App\Http\Resources\PayableResource;
use App\Models\Payable;
use App\Services\PayableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PayableController extends Controller
{
    public function __construct(private readonly PayableService $payableService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payable::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'paid', 'overdue'])],
            'type' => ['nullable', Rule::enum(PayableType::class)],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'professional_id' => ['nullable', 'integer', 'exists:professionals,id'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date', 'after_or_equal:due_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $payables = Payable::query()
            ->with(['supplier', 'professional', 'creator', 'payer'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($builder) use ($search) {
                    $builder->where('description', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = $request->string('status')->toString();

                if ($status === 'overdue') {
                    $query->where('status', PayableStatus::Pending)
                        ->whereDate('due_date', '<', now()->toDateString());

                    return;
                }

                $query->where('status', $status);
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('professional_id'), fn ($q) => $q->where('professional_id', $request->integer('professional_id')))
            ->when($request->filled('due_from'), fn ($q) => $q->whereDate('due_date', '>=', $request->date('due_from')))
            ->when($request->filled('due_to'), fn ($q) => $q->whereDate('due_date', '<=', $request->date('due_to')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return PayableResource::collection($payables);
    }

    public function store(StorePayableRequest $request): JsonResponse
    {
        $this->authorize('create', Payable::class);

        $payable = $this->payableService->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'Conta a pagar criada com sucesso.',
            'data' => new PayableResource($payable->load(['supplier', 'professional', 'creator', 'payer'])),
        ], 201);
    }

    public function show(Payable $payable): JsonResponse
    {
        $this->authorize('view', $payable);

        return response()->json([
            'data' => new PayableResource($payable->load(['supplier', 'professional', 'creator', 'payer'])),
        ]);
    }

    public function update(UpdatePayableRequest $request, Payable $payable): JsonResponse
    {
        $this->authorize('update', $payable);

        $payable = $this->payableService->update($payable, $request->validated());

        return response()->json([
            'message' => 'Conta a pagar atualizada com sucesso.',
            'data' => new PayableResource($payable),
        ]);
    }

    public function destroy(Payable $payable): Response
    {
        $this->authorize('delete', $payable);

        $this->payableService->delete($payable);

        return response()->noContent();
    }

    public function pay(MarkPayablePaidRequest $request, Payable $payable): JsonResponse
    {
        $this->authorize('markAsPaid', $payable);

        $payable = $this->payableService->markAsPaid(
            $payable,
            $request->user(),
            $request->validated('paid_at')
        );

        return response()->json([
            'message' => 'Conta a pagar marcada como paga.',
            'data' => new PayableResource($payable),
        ]);
    }
}
