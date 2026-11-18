<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportFilterRequest;
use App\Services\ReportExportService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportExportService $exports,
    ) {}

    public function overview(ReportFilterRequest $request): JsonResponse|Response
    {
        $data = $this->reports->overview(
            $request->input('from'),
            $request->input('to'),
        );

        return $this->respond($request, 'overview', $data, [
            'Cash Income', 'Cash Expense', 'Cash Net', 'Appointments', 'Sales Paid',
        ], [
            [
                $data['summary']['cash_income'],
                $data['summary']['cash_expense'],
                $data['summary']['cash_net'],
                $data['summary']['appointments_total'],
                $data['summary']['sales_paid'],
            ],
        ], 'reports.overview');
    }

    public function appointments(ReportFilterRequest $request): JsonResponse|Response
    {
        $data = $this->reports->appointments($request->validated());

        $rows = collect($data['rows'])->map(fn (array $row) => [
            $row['id'],
            $row['start_time'],
            $row['client'],
            $row['service'],
            $row['professional'],
            $row['status'],
            $row['total_price'],
        ]);

        return $this->respond(
            $request,
            'appointments',
            $data,
            ['ID', 'Data', 'Cliente', 'Serviço', 'Profissional', 'Estado', 'Valor'],
            $rows,
            'reports.appointments',
        );
    }

    public function sales(ReportFilterRequest $request): JsonResponse|Response
    {
        $data = $this->reports->sales($request->validated());

        $rows = collect($data['rows'])->map(fn (array $row) => [
            $row['id'],
            $row['created_at'],
            $row['client'],
            $row['seller'],
            $row['payment_method'],
            $row['status'],
            $row['total_amount'],
            $row['items_count'],
        ]);

        return $this->respond(
            $request,
            'sales',
            $data,
            ['ID', 'Data', 'Cliente', 'Vendedor', 'Pagamento', 'Estado', 'Total', 'Itens'],
            $rows,
            'reports.sales',
        );
    }

    public function commissions(ReportFilterRequest $request): JsonResponse|Response
    {
        $data = $this->reports->commissions($request->validated());

        $rows = collect($data['rows'])->map(fn (array $row) => [
            $row['origin_id'] ?? '',
            $row['date'] ?? '',
            $row['professional']['name'] ?? '',
            $row['service']['name'] ?? '',
            $row['base_amount'] ?? '',
            $row['percentage'] ?? '',
            $row['commission_amount'] ?? '',
            $row['source'] ?? '',
            $row['financial_status'] ?? '',
        ]);

        return $this->respond(
            $request,
            'commissions',
            $data,
            ['Origin ID', 'Data', 'Profissional', 'Serviço', 'Base', '%', 'Comissão', 'Origem %', 'Estado'],
            $rows,
            'reports.commissions',
        );
    }

    public function financial(ReportFilterRequest $request): JsonResponse|Response
    {
        $data = $this->reports->financial($request->validated());
        $s = $data['summary'];

        $rows = [
            ['Receivables total', $s['receivables_total']],
            ['Receivables received', $s['receivables_received']],
            ['Receivables pending', $s['receivables_pending']],
            ['Payables total', $s['payables_total']],
            ['Payables paid', $s['payables_paid']],
            ['Payables pending', $s['payables_pending']],
            ['Sales total', $s['sales_total']],
            ['Sales paid', $s['sales_paid']],
            ['Commissions total', $s['commissions_total']],
            ['Cash income', $s['cash_income']],
            ['Cash expense', $s['cash_expense']],
            ['Cash net', $s['cash_net']],
            ['Operational result', $s['operational_result']],
        ];

        return $this->respond(
            $request,
            'financial',
            $data,
            ['Métrica', 'Valor'],
            $rows,
            'reports.financial',
        );
    }

    public function cash(ReportFilterRequest $request): JsonResponse|Response
    {
        $data = $this->reports->cashMonthly(
            $request->filled('year') ? $request->integer('year') : null,
            $request->filled('month') ? $request->integer('month') : null,
        );

        $rows = collect($data['days'])->map(fn (array $day) => [
            $day['date'],
            $day['registers_count'],
            $day['opening_amount'],
            $day['income'],
            $day['expense'],
            $day['expected_balance'],
            $day['closing_amount'],
        ]);

        return $this->respond(
            $request,
            'cash-monthly',
            $data,
            ['Data', 'Caixas', 'Abertura', 'Entradas', 'Saídas', 'Esperado', 'Fecho'],
            $rows,
            'reports.cash',
        );
    }

    /**
     * @param  iterable<int, array<int, mixed>>  $csvRows
     * @param  list<string>  $csvHeaders
     * @param  array<string, mixed>  $data
     */
    private function respond(
        ReportFilterRequest $request,
        string $basename,
        array $data,
        array $csvHeaders,
        iterable $csvRows,
        string $pdfView,
    ): JsonResponse|Response {
        $format = $request->input('format', 'json');

        if ($format === 'csv') {
            return $this->exports->csv(
                "relatorio_{$basename}_{$data['meta']['from']}_{$data['meta']['to']}",
                $csvHeaders,
                $csvRows,
            );
        }

        if ($format === 'pdf') {
            return $this->exports->pdf($pdfView, [
                'report' => $data,
                'title' => ucfirst(str_replace('-', ' ', $basename)),
                'salon_name' => $data['meta']['salon_name'] ?? null,
                'currency' => $data['meta']['currency'] ?? null,
            ], "relatorio_{$basename}_{$data['meta']['from']}_{$data['meta']['to']}");
        }

        return response()->json(['data' => $data]);
    }
}
