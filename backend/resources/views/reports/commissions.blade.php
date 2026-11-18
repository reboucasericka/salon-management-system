@extends('reports.layout')

@section('content')
    <div class="kpis">
        <div class="kpi"><span>Preview</span><strong>{{ $report['summary']['preview_total'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Payables</span><strong>{{ $report['summary']['payables_total'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Pagas</span><strong>{{ $report['summary']['payables_paid'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Pendentes</span><strong>{{ $report['summary']['payables_pending'] }} {{ $currency }}</strong></div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Data</th><th>Profissional</th><th>Serviço</th><th>Base</th><th>%</th><th>Comissão</th><th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $row)
                <tr>
                    <td>{{ $row['date'] ?? '—' }}</td>
                    <td>{{ $row['professional']['name'] ?? '—' }}</td>
                    <td>{{ $row['service']['name'] ?? '—' }}</td>
                    <td>{{ $row['base_amount'] ?? '—' }}</td>
                    <td>{{ $row['percentage'] ?? '—' }}</td>
                    <td>{{ $row['commission_amount'] ?? '—' }}</td>
                    <td>{{ $row['financial_status'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Sem comissões no período.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
