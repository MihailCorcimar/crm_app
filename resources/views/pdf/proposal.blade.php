<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Proposta {{ $proposal->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { width: 100%; margin-bottom: 12px; }
        .header::after { content: ""; display: block; clear: both; }
        .logo { float: left; width: 90px; height: 90px; object-fit: contain; }
        .company { float: right; width: 72%; text-align: right; }
        .company-name { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        h1 { margin-bottom: 8px; }
        .meta { margin-bottom: 16px; }
        .meta div { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f5f5f5; }
        .total { margin-top: 12px; font-weight: bold; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($company['logo_data_uri']))
            <img src="{{ $company['logo_data_uri'] }}" alt="Logo empresa" class="logo">
        @endif

        <div class="company">
            <div class="company-name">{{ $company['name'] ?? 'App de Gestao' }}</div>
            @if(!empty($company['address']))
                <div>{{ $company['address'] }}</div>
            @endif
            <div>
                {{ trim(($company['postal_code'] ?? '').' '.($company['city'] ?? '')) ?: '-' }}
            </div>
            @if(!empty($company['tax_number']))
                <div><strong>NIF:</strong> {{ $company['tax_number'] }}</div>
            @endif
        </div>
    </div>

    <h1>Proposta #{{ $proposal->number }}</h1>
    <div class="meta">
        <div><strong>Data:</strong> {{ optional($proposal->proposal_date)->format('Y-m-d') ?? '-' }}</div>
        <div><strong>Validade:</strong> {{ optional($proposal->valid_until)->format('Y-m-d') }}</div>
        <div><strong>Cliente:</strong> {{ $proposal->customer?->name }}</div>
        <div><strong>Estado:</strong> {{ $proposal->status === 'closed' ? 'Fechado' : 'Rascunho' }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Artigo</th>
                <th>Fornecedor</th>
                <th>Qtd</th>
                <th>Preco</th>
                <th>Preco custo</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($proposal->lines as $line)
                <tr>
                    <td>{{ ($line->item?->reference ?? '-') . ' - ' . ($line->item?->name ?? '-') }}</td>
                    <td>{{ $line->supplier?->name ?? '-' }}</td>
                    <td>{{ number_format((float) $line->quantity, 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $line->sale_price, 2, ',', '.') }} €</td>
                    <td>{{ number_format((float) $line->cost_price, 2, ',', '.') }} €</td>
                    <td>{{ number_format((float) $line->line_total, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">Total: {{ number_format((float) $proposal->total, 2, ',', '.') }} €</div>
</body>
</html>
