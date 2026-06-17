<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title', config('app.name'))</title>
    <style>
        /* Pitfall 4: Dompdf cannot load the Vite stylesheet. Plain CSS only. */
        @page { margin: 140px 30px 80px 30px; }

        * { font-family: DejaVu Sans, sans-serif; }

        body {
            font-size: 11px;
            color: #222;
            margin: 0;
        }

        .pdf-header {
            position: fixed;
            top: 30px;
            left: 30px;
            right: 30px;
            height: 90px;
            border-bottom: 1px solid #999;
            font-size: 11px;
        }

        .pdf-header h1 {
            font-size: 14px;
            margin: 0 0 4px 0;
        }

        .pdf-footer {
            position: fixed;
            bottom: 30px;
            left: 30px;
            right: 30px;
            border-top: 1px solid #999;
            font-size: 9px;
            color: #666;
            display: flex;
            justify-content: space-between;
            padding-top: 4px;
        }

        /* D-13: only counter(page) works; counter(pages) does NOT. Footer = "Page N". */
        .pdf-footer .page-num::after { content: "Page " counter(page); }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            text-align: left;
        }

        th {
            background: #f3f4f6;
            font-weight: bold;
        }

        .num { text-align: right; white-space: nowrap; }

        /* D-13 column compaction for wide tables (Monthly Report per-member). */
        .pdf-table-compact { font-size: 9px; }
        .pdf-table-compact th, .pdf-table-compact td { padding: 2px 4px; }

        .totals {
            margin-top: 12px;
            font-weight: bold;
        }

        .section {
            margin-top: 16px;
        }

        .section h2 {
            font-size: 12px;
            margin: 0 0 6px 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 2px;
        }

        .math-line {
            background: #f9fafb;
            padding: 6px;
            border: 1px solid #e5e7eb;
            margin: 8px 0;
            font-size: 12px;
        }

        .totals-grid {
            margin-top: 10px;
        }

        .totals-grid div {
            margin-bottom: 2px;
        }

        .label { color: #555; }
    </style>
</head>
<body>
    <div class="pdf-header">
        <h1>{{ $mess->name ?? config('app.name') }}</h1>
        <div>{{ $reportTitle ?? '' }}@if (isset($period) && $period) — {{ $period }}@endif</div>
    </div>

    <div class="pdf-footer">
        <span class="page-num"></span>
        <span>{{ __('Generated') }}: {{ $generatedAt ?? now()->format('d-m-Y H:i') }}</span>
    </div>

    @yield('report-body')
</body>
</html>
