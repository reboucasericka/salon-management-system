@extends('reports.layout')

@section('content')
    <div class="kpis">
        <div class="kpi"><span>Total</span><strong>{{ $report['summary']['total'] }}</strong></div>
        <div class="kpi"><span>Pendentes</span><strong>{{ $report['summary']['pending'] }}</strong></div>
        <div class="kpi"><span>Confirmados</span><strong>{{ $report['summary']['confirmed'] }}</strong></div>
        <div class="kpi"><span>Concluídos</span><strong>{{ $report['summary']['completed'] }}</strong></div>
        <div class="kpi"><span>Cancelados</span><strong>{{ $report['summary']['canceled'] }}</strong></div>
        <div class="kpi"><span>Receita concluída</span><strong>{{ $report['summary']['revenue_completed'] }} {{ $currency }}</strong></div>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Data</th><th>Cliente</th><th>Serviço</th><th>Profissional</th><th>Estado</th><th>Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $row)
                <tr>
                    <td>{{ $row['id'] }}</td>
                    <td>{{ $row['start_time'] }}</td>
                    <td>{{ $row['client'] }}</td>
                    <td>{{ $row['service'] }}</td>
                    <td>{{ $row['professional'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['total_price'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Sem agendamentos no período.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
