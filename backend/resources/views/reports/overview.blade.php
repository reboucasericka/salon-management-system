@extends('reports.layout')

@section('content')
    <div class="kpis">
        <div class="kpi"><span>Entradas</span><strong>{{ $report['summary']['cash_income'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Saídas</span><strong>{{ $report['summary']['cash_expense'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Saldo caixa</span><strong>{{ $report['summary']['cash_net'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Agendamentos</span><strong>{{ $report['summary']['appointments_total'] }}</strong></div>
        <div class="kpi"><span>Vendas pagas</span><strong>{{ $report['summary']['sales_paid'] }} {{ $currency }}</strong></div>
    </div>
    <h3>Top serviços</h3>
    <table>
        <thead><tr><th>Serviço</th><th>Total</th></tr></thead>
        <tbody>
            @forelse($report['top_services'] as $row)
                <tr><td>{{ $row['service'] ?? '—' }}</td><td>{{ $row['total'] }}</td></tr>
            @empty
                <tr><td colspan="2">Sem dados.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
