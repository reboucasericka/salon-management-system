@extends('reports.layout')

@section('content')
    <div class="kpis">
        <div class="kpi"><span>Vendas</span><strong>{{ $report['summary']['total_sales'] }}</strong></div>
        <div class="kpi"><span>Pagas</span><strong>{{ $report['summary']['paid_sales'] }}</strong></div>
        <div class="kpi"><span>Total</span><strong>{{ $report['summary']['total_amount'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Ticket médio</span><strong>{{ $report['summary']['average_ticket'] }} {{ $currency }}</strong></div>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Data</th><th>Cliente</th><th>Vendedor</th><th>Pagamento</th><th>Estado</th><th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $row)
                <tr>
                    <td>{{ $row['id'] }}</td>
                    <td>{{ $row['created_at'] }}</td>
                    <td>{{ $row['client'] }}</td>
                    <td>{{ $row['seller'] }}</td>
                    <td>{{ $row['payment_method'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['total_amount'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Sem vendas no período.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
