@extends('reports.layout')

@section('content')
    <div class="kpis">
        <div class="kpi"><span>Entradas</span><strong>{{ $report['summary']['income'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Saídas</span><strong>{{ $report['summary']['expense'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Aberturas</span><strong>{{ $report['summary']['opening_amount'] }} {{ $currency }}</strong></div>
        <div class="kpi"><span>Fechos</span><strong>{{ $report['summary']['closing_amount'] }} {{ $currency }}</strong></div>
    </div>
    <p>Nota: balancete operacional de caixas (não contabilidade formal).</p>
    <table>
        <thead>
            <tr>
                <th>Data</th><th>Caixas</th><th>Abertura</th><th>Entradas</th><th>Saídas</th><th>Esperado</th><th>Fecho</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['days'] as $day)
                <tr>
                    <td>{{ $day['date'] }}</td>
                    <td>{{ $day['registers_count'] }}</td>
                    <td>{{ $day['opening_amount'] }}</td>
                    <td>{{ $day['income'] }}</td>
                    <td>{{ $day['expense'] }}</td>
                    <td>{{ $day['expected_balance'] }}</td>
                    <td>{{ $day['closing_amount'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Sem movimentos no mês.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
