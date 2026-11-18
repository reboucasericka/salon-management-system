<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\CommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function __construct(private readonly CommissionService $commissionService) {}

    /**
     * Pré-visualização sob demanda (appointments concluídos).
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role === UserRole::Admin, 403);

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'professional_id' => ['nullable', 'integer', 'exists:professionals,id'],
            'financial_status' => ['nullable', 'string', 'in:calculated,payable_pending,paid'],
        ]);

        $rows = $this->commissionService->previewFromCompletedAppointments(
            $request->date('from')?->toDateString(),
            $request->date('to')?->toDateString(),
            $request->filled('professional_id') ? $request->integer('professional_id') : null,
            $request->string('financial_status')->toString() ?: null,
        );

        $total = $rows->sum(fn (array $row) => (float) $row['commission_amount']);

        return response()->json([
            'data' => $rows->values(),
            'meta' => [
                'mode' => 'calculated_preview',
                'note' => 'Valores calculados a partir de agendamentos concluídos. Contas a pagar só existem após geração manual.',
                'total_commission' => number_format($total, 2, '.', ''),
                'count' => $rows->count(),
            ],
        ]);
    }

    /**
     * Geração MANUAL de Payables tipo commission (sem automação no legado).
     */
    public function generate(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role === UserRole::Admin, 403);

        $data = $request->validate([
            'appointment_ids' => ['nullable', 'array', 'min:1'],
            'appointment_ids.*' => ['integer', 'exists:appointments,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'professional_id' => ['nullable', 'integer', 'exists:professionals,id'],
        ]);

        if (empty($data['appointment_ids']) && empty($data['from']) && empty($data['to']) && empty($data['professional_id'])) {
            return response()->json([
                'message' => 'Indique appointment_ids e/ou filtros de período/profissional.',
                'errors' => [
                    'appointment_ids' => ['É necessário selecionar agendamentos ou filtros.'],
                ],
            ], 422);
        }

        $result = $this->commissionService->generatePayables(
            $request->user(),
            $data['appointment_ids'] ?? null,
            isset($data['from']) ? $request->date('from')?->toDateString() : null,
            isset($data['to']) ? $request->date('to')?->toDateString() : null,
            isset($data['professional_id']) ? (int) $data['professional_id'] : null,
        );

        return response()->json([
            'message' => 'Geração de comissões concluída.',
            'data' => $result,
            'meta' => [
                'created_count' => count($result['created']),
                'skipped_count' => count($result['skipped']),
            ],
        ], 201);
    }
}
