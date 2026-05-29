<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ config('brand.name') }} — Relatório do Período</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { padding: 32px; }
        .header { border-bottom: 2px solid black; padding-bottom: 12px; margin-bottom: 24px; }
        .brand-name { font-size: 22px; font-weight: bold; }
        .brand-tagline { font-size: 11px; letter-spacing: 0.05em; margin-top: 2px; }
        .period { font-size: 12px; margin-top: 6px; }
        h2 { font-size: 14px; font-weight: bold; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; text-align: left; border: 1px solid black; font-size: 12px; }
        th { font-weight: bold; background-color: lightgrey; }
        td.value { text-align: right; font-weight: bold; }
        .footer { margin-top: 32px; font-size: 10px; border-top: 1px solid black; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand-name">{{ config('brand.name') }}</div>
        <div class="brand-tagline">{{ config('brand.tagline') }}</div>
        <div class="period">
            Período: {{ $start->format('d/m/Y') }} a {{ $end->format('d/m/Y') }}
        </div>
    </div>

    <h2>KPIs do Período</h2>

    <table>
        <thead>
            <tr>
                <th>Indicador</th>
                <th style="text-align: right;">Valor</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Receita total (vendas confirmadas)</td>
                <td class="value">R$&nbsp;{{ number_format($revenue, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Número de vendas confirmadas</td>
                <td class="value">{{ $count }}</td>
            </tr>
            <tr>
                <td>Ticket médio</td>
                <td class="value">R$&nbsp;{{ number_format($avgTicket, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Share de água na receita</td>
                <td class="value">{{ number_format($waterShare, 1, ',', '.') }}%</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        {{ config('brand.address.one_line') }} &mdash; {{ config('brand.hours.one_line') }}
        &mdash; {{ config('brand.phones.landline') }}
        &mdash; Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
