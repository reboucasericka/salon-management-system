@extends('reports.layout')

@section('content')
    <div class="kpis">
        <div class="kpi"><span>Vendas pagas</span><strong>{{ $report['summary']['sales_paid'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Payables pagas</span><strong>{{ $report['summary']['payables_paid'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Caixa líquido</span><strong>{{ $report['summary']['cash_net'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Resultado operacional</span><strong>{{ $report['summary']['operational_result'] }} {{ $currency }}</strong></div>
    </div>
    <p style="color:#666;font-size:11px;">{{ $report['summary']['operational_result_note'] }}</p>
    <table>
        <thead><tr><th>Métrica</th><th>Valor</th></tr></thead>
        <tbody>
            @foreach($report['summary'] as $key => $value)
                @if($key !== 'operational_result_note')
                    <tr><td>{{ $key }}</td><td>{{ $value }}</td></tr>
                @endif
            @endforeach
        </tbody>
    </table>
@endsection
