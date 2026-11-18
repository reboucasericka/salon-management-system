<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Relatório' }} — {{ $salon_name ?? config('app.name') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        .meta { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .kpis { margin: 12px 0; }
        .kpi { display: inline-block; margin-right: 16px; margin-bottom: 8px; }
        .kpi strong { display: block; font-size: 14px; }
    </style>
</head>
<body>
    <h1>{{ $title ?? 'Relatório' }}</h1>
    <div class="meta">
        <div>{{ $salon_name ?? '' }} · Moeda: {{ $currency ?? 'EUR' }}</div>
        @if(!empty($report['meta']))
            <div>
                Período: {{ $report['meta']['from'] ?? '—' }} → {{ $report['meta']['to'] ?? '—' }}
                · TZ: {{ $report['meta']['timezone'] ?? '—' }}
            </div>
        @endif
    </div>
    @yield('content')
</body>
</html>
