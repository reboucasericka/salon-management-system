<?php

namespace App\Services;

use App\Enums\CashRegisterStatus;
use App\Enums\CashTransactionType;
use App\Enums\PayableStatus;
use App\Enums\PayableType;
use App\Enums\ReceivableStatus;
use App\Enums\SaleStatus;
use App\Models\Appointment;
use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Relatórios operacionais (paridade com AdminReports C# funcional).
 *
 * Fontes: appointments, sales, payables, receivables, cash_*.
 * Timezone/currency/salon via SettingsService.
 *
 * NetProfit legado: view órfã sem fórmula no controller — NÃO recriado como report separado.
 * MonthlyTrialBalance: agregado mensal de caixas (operacional, não contabilidade formal).
 */
class ReportService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CommissionService $commissions,
        private readonly CashService $cash,
    ) {}

    /**
     * @return array{from: Carbon, to: Carbon, timezone: string}
     */
    public function resolvePeriod(?string $from, ?string $to): array
    {
        $tz = $this->settings->timezone();
        $fromDt = $from
            ? Carbon::parse($from, $tz)->startOfDay()
            : Carbon::now($tz)->subDays(29)->startOfDay();
        $toDt = $to
            ? Carbon::parse($to, $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();

        if ($toDt->lt($fromDt)) {
            [$fromDt, $toDt] = [$toDt->copy()->startOfDay(), $fromDt->copy()->endOfDay()];
        }

        return [
            'from' => $fromDt->copy()->utc(),
            'to' => $toDt->copy()->utc(),
            'timezone' => $tz,
            'from_local' => $fromDt->toDateString(),
            'to_local' => $toDt->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(array $period): array
    {
        return [
            'salon_name' => $this->settings->salonName(),
            'currency' => $this->settings->currency(),
            'timezone' => $period['timezone'],
            'from' => $period['from_local'],
            'to' => $period['to_local'],
            'generated_at' => Carbon::now($period['timezone'])->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function appointments(array $filters = []): array
    {
        $period = $this->resolvePeriod($filters['from'] ?? null, $filters['to'] ?? null);

        $query = Appointment::query()
            ->with(['client:id,name,email', 'service:id,name', 'professional:id,name'])
            ->whereBetween('start_time', [$period['from'], $period['to']])
            ->when(! empty($filters['professional_id']), fn ($q) => $q->where('professional_id', (int) $filters['professional_id']))
            ->when(! empty($filters['service_id']), fn ($q) => $q->where('service_id', (int) $filters['service_id']))
            ->when(! empty($filters['client_id']), fn ($q) => $q->where('client_id', (int) $filters['client_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderBy('start_time');

        $rows = $query->get();

        $summary = [
            'total' => $rows->count(),
            'pending' => $rows->where('status', Appointment::STATUS_PENDING)->count(),
            'confirmed' => $rows->where('status', Appointment::STATUS_CONFIRMED)->count(),
            'completed' => $rows->where('status', Appointment::STATUS_COMPLETED)->count(),
            'canceled' => $rows->where('status', Appointment::STATUS_CANCELED)->count(),
            'revenue_completed' => number_format(
                (float) $rows->where('status', Appointment::STATUS_COMPLETED)->sum('total_price'),
                2,
                '.',
                ''
            ),
        ];

        $byProfessional = $rows->groupBy('professional_id')->map(function (Collection $group) {
            $first = $group->first();

            return [
                'professional_id' => $first?->professional_id,
                'professional' => $first?->professional?->name,
                'total' => $group->count(),
                'completed' => $group->where('status', Appointment::STATUS_COMPLETED)->count(),
            ];
        })->values();

        return [
            'meta' => $this->meta($period),
            'summary' => $summary,
            'by_professional' => $byProfessional,
            'rows' => $rows->map(fn (Appointment $a) => [
                'id' => $a->id,
                'start_time' => $a->start_time?->timezone($period['timezone'])->toIso8601String(),
                'status' => $a->status,
                'total_price' => $a->total_price,
                'client' => $a->client?->name,
                'service' => $a->service?->name,
                'professional' => $a->professional?->name,
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function sales(array $filters = []): array
    {
        $period = $this->resolvePeriod($filters['from'] ?? null, $filters['to'] ?? null);

        $query = Sale::query()
            ->with(['client:id,name', 'user:id,first_name,last_name', 'items'])
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->when(! empty($filters['client_id']), fn ($q) => $q->where('client_id', (int) $filters['client_id']))
            ->when(! empty($filters['payment_method']), fn ($q) => $q->where('payment_method', $filters['payment_method']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('created_at');

        $rows = $query->get();
        $paid = $rows->where('status', SaleStatus::Paid);

        $summary = [
            'total_sales' => $rows->count(),
            'paid_sales' => $paid->count(),
            'total_amount' => number_format((float) $rows->sum('total_amount'), 2, '.', ''),
            'paid_amount' => number_format((float) $paid->sum('total_amount'), 2, '.', ''),
            'total_items' => (int) $rows->sum(fn (Sale $s) => $s->items->sum('quantity')),
            'average_ticket' => $paid->count() > 0
                ? number_format((float) $paid->avg('total_amount'), 2, '.', '')
                : '0.00',
        ];

        $byPayment = $rows->groupBy(fn (Sale $s) => $s->payment_method?->value ?? 'other')
            ->map(fn (Collection $group, string $method) => [
                'payment_method' => $method,
                'count' => $group->count(),
                'total' => number_format((float) $group->sum('total_amount'), 2, '.', ''),
            ])->values();

        return [
            'meta' => $this->meta($period),
            'summary' => $summary,
            'by_payment_method' => $byPayment,
            'rows' => $rows->map(fn (Sale $s) => [
                'id' => $s->id,
                'created_at' => $s->created_at?->timezone($period['timezone'])->toIso8601String(),
                'client' => $s->client?->name,
                'seller' => $s->user?->full_name,
                'payment_method' => $s->payment_method?->value,
                'status' => $s->status?->value,
                'total_amount' => $s->total_amount,
                'items_count' => $s->items->sum('quantity'),
            ])->values(),
        ];
    }

    /**
     * Comissões: preview CommissionService + estado de Payables.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function commissions(array $filters = []): array
    {
        $period = $this->resolvePeriod($filters['from'] ?? null, $filters['to'] ?? null);

        $preview = $this->commissions->previewFromCompletedAppointments(
            $period['from_local'],
            $period['to_local'],
            ! empty($filters['professional_id']) ? (int) $filters['professional_id'] : null,
            $filters['financial_status'] ?? null,
        );

        $payables = Payable::query()
            ->with('professional:id,name')
            ->where('type', PayableType::Commission)
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->when(! empty($filters['professional_id']), fn ($q) => $q->where('professional_id', (int) $filters['professional_id']))
            ->get();

        $summary = [
            'preview_count' => $preview->count(),
            'preview_total' => number_format((float) $preview->sum(fn ($r) => (float) $r['commission_amount']), 2, '.', ''),
            'payables_count' => $payables->count(),
            'payables_total' => number_format((float) $payables->sum('amount'), 2, '.', ''),
            'payables_paid' => number_format((float) $payables->where('status', PayableStatus::Paid)->sum('amount'), 2, '.', ''),
            'payables_pending' => number_format((float) $payables->where('status', PayableStatus::Pending)->sum('amount'), 2, '.', ''),
        ];

        $byProfessional = $preview->groupBy('professional_id')->map(function (Collection $group) {
            $first = $group->first();

            return [
                'professional_id' => $first['professional_id'] ?? null,
                'professional' => $first['professional']['name'] ?? null,
                'count' => $group->count(),
                'total' => number_format((float) $group->sum(fn ($r) => (float) $r['commission_amount']), 2, '.', ''),
            ];
        })->values();

        return [
            'meta' => $this->meta($period),
            'summary' => $summary,
            'by_professional' => $byProfessional,
            'rows' => $preview->values(),
        ];
    }

    /**
     * Resumo financeiro alinhado a CashFlowReport + FinancialSummary do legado.
     *
     * Fórmulas:
     * - receivables_* : Receivable.created_at no período
     * - payables_* : Payable.created_at no período
     * - sales_* : Sale.created_at no período
     * - cash_net : cash_transactions income - expense no período
     * - operational_result (DECISÃO): sales_paid_amount - payables_paid_amount
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function financial(array $filters = []): array
    {
        $period = $this->resolvePeriod($filters['from'] ?? null, $filters['to'] ?? null);

        $receivables = Receivable::query()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->get();

        $payables = Payable::query()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->get();

        $sales = Sale::query()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->get();

        $cash = CashTransaction::query()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->get();

        $cashIncome = (float) $cash->where('type', CashTransactionType::Income)->sum('amount');
        $cashExpense = (float) $cash->where('type', CashTransactionType::Expense)->sum('amount');
        $salesPaid = (float) $sales->where('status', SaleStatus::Paid)->sum('total_amount');
        $payablesPaid = (float) $payables->where('status', PayableStatus::Paid)->sum('amount');
        $commissions = $payables->where('type', PayableType::Commission);

        $summary = [
            'receivables_total' => number_format((float) $receivables->sum('amount'), 2, '.', ''),
            'receivables_received' => number_format((float) $receivables->where('status', ReceivableStatus::Received)->sum('amount'), 2, '.', ''),
            'receivables_pending' => number_format((float) $receivables->where('status', ReceivableStatus::Pending)->sum('amount'), 2, '.', ''),
            'payables_total' => number_format((float) $payables->sum('amount'), 2, '.', ''),
            'payables_paid' => number_format($payablesPaid, 2, '.', ''),
            'payables_pending' => number_format((float) $payables->where('status', PayableStatus::Pending)->sum('amount'), 2, '.', ''),
            'sales_total' => number_format((float) $sales->sum('total_amount'), 2, '.', ''),
            'sales_paid' => number_format($salesPaid, 2, '.', ''),
            'sales_count' => $sales->count(),
            'commissions_total' => number_format((float) $commissions->sum('amount'), 2, '.', ''),
            'commissions_paid' => number_format((float) $commissions->where('status', PayableStatus::Paid)->sum('amount'), 2, '.', ''),
            'commissions_pending' => number_format((float) $commissions->where('status', PayableStatus::Pending)->sum('amount'), 2, '.', ''),
            'cash_income' => number_format($cashIncome, 2, '.', ''),
            'cash_expense' => number_format($cashExpense, 2, '.', ''),
            'cash_net' => number_format($cashIncome - $cashExpense, 2, '.', ''),
            'operational_result' => number_format($salesPaid - $payablesPaid, 2, '.', ''),
            'operational_result_note' => 'DECISÃO DE MIGRAÇÃO: vendas pagas − contas a pagar pagas no período. NetProfit legado não tinha fórmula no controller.',
        ];

        $payablesByType = $payables->groupBy(fn (Payable $p) => $p->type?->value ?? 'other')
            ->map(fn (Collection $group, string $type) => [
                'type' => $type,
                'count' => $group->count(),
                'total' => number_format((float) $group->sum('amount'), 2, '.', ''),
                'paid' => number_format((float) $group->where('status', PayableStatus::Paid)->sum('amount'), 2, '.', ''),
                'pending' => number_format((float) $group->where('status', PayableStatus::Pending)->sum('amount'), 2, '.', ''),
            ])->values();

        return [
            'meta' => $this->meta($period),
            'summary' => $summary,
            'payables_by_type' => $payablesByType,
        ];
    }

    /**
     * Balancete mensal operacional de caixas (não contabilidade formal).
     *
     * @return array<string, mixed>
     */
    public function cashMonthly(?int $year = null, ?int $month = null): array
    {
        $tz = $this->settings->timezone();
        $now = Carbon::now($tz);
        $year ??= (int) $now->year;
        $month ??= (int) $now->month;

        $startLocal = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfMonth();
        $endLocal = $startLocal->copy()->endOfMonth();
        $start = $startLocal->copy()->utc();
        $end = $endLocal->copy()->utc();

        $days = [];
        $cursor = $startLocal->copy();

        while ($cursor->lte($endLocal)) {
            $dayReport = $this->cash->dailyReport($cursor->copy());
            $days[] = [
                'date' => $cursor->toDateString(),
                'registers_count' => count($dayReport['registers']),
                'opening_amount' => number_format((float) $dayReport['totals']['opening_amount'], 2, '.', ''),
                'income' => number_format((float) $dayReport['totals']['income'], 2, '.', ''),
                'expense' => number_format((float) $dayReport['totals']['expense'], 2, '.', ''),
                'expected_balance' => number_format((float) $dayReport['totals']['expected_balance'], 2, '.', ''),
                'closing_amount' => number_format((float) $dayReport['totals']['closing_amount'], 2, '.', ''),
            ];
            $cursor->addDay();
        }

        $openCount = CashRegister::query()
            ->whereBetween('opened_at', [$start, $end])
            ->where('status', CashRegisterStatus::Open)
            ->count();
        $closedCount = CashRegister::query()
            ->whereBetween('opened_at', [$start, $end])
            ->where('status', CashRegisterStatus::Closed)
            ->count();

        $totals = [
            'opening_amount' => number_format(array_sum(array_map(fn ($d) => (float) $d['opening_amount'], $days)), 2, '.', ''),
            'income' => number_format(array_sum(array_map(fn ($d) => (float) $d['income'], $days)), 2, '.', ''),
            'expense' => number_format(array_sum(array_map(fn ($d) => (float) $d['expense'], $days)), 2, '.', ''),
            'expected_balance' => number_format(array_sum(array_map(fn ($d) => (float) $d['expected_balance'], $days)), 2, '.', ''),
            'closing_amount' => number_format(array_sum(array_map(fn ($d) => (float) $d['closing_amount'], $days)), 2, '.', ''),
            'registers_open' => $openCount,
            'registers_closed' => $closedCount,
        ];

        return [
            'meta' => array_merge($this->meta([
                'timezone' => $tz,
                'from_local' => $startLocal->toDateString(),
                'to_local' => $endLocal->toDateString(),
            ]), [
                'year' => $year,
                'month' => $month,
            ]),
            'summary' => $totals,
            'days' => $days,
        ];
    }

    /**
     * Overview (Index legado): cash + appointments top services.
     *
     * @return array<string, mixed>
     */
    public function overview(?string $from = null, ?string $to = null): array
    {
        $period = $this->resolvePeriod($from, $to);

        $cash = CashTransaction::query()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->get();

        $income = (float) $cash->where('type', CashTransactionType::Income)->sum('amount');
        $expense = (float) $cash->where('type', CashTransactionType::Expense)->sum('amount');

        $topServices = Appointment::query()
            ->selectRaw('service_id, count(*) as total')
            ->whereBetween('start_time', [$period['from'], $period['to']])
            ->whereNotNull('service_id')
            ->groupBy('service_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('service:id,name')
            ->get()
            ->map(fn ($row) => [
                'service_id' => $row->service_id,
                'service' => $row->service?->name,
                'total' => (int) $row->total,
            ]);

        return [
            'meta' => $this->meta($period),
            'summary' => [
                'cash_income' => number_format($income, 2, '.', ''),
                'cash_expense' => number_format($expense, 2, '.', ''),
                'cash_net' => number_format($income - $expense, 2, '.', ''),
                'appointments_total' => Appointment::query()
                    ->whereBetween('start_time', [$period['from'], $period['to']])
                    ->count(),
                'sales_paid' => number_format(
                    (float) Sale::query()
                        ->whereBetween('created_at', [$period['from'], $period['to']])
                        ->where('status', SaleStatus::Paid)
                        ->sum('total_amount'),
                    2,
                    '.',
                    ''
                ),
            ],
            'top_services' => $topServices,
        ];
    }
}
