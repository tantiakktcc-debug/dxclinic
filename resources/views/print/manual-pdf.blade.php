<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'User Operation Manual' }} - DxClinic</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 20mm 15mm 20mm 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2.5px solid #0f766e;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .clinic-brand {
            font-size: 18pt;
            font-weight: 900;
            color: #0f766e;
            letter-spacing: 0.5px;
        }
        .manual-title {
            font-size: 14pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 3px;
        }
        .meta-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 20px;
            font-size: 8.5pt;
            color: #475569;
        }
        .meta-box table { width: 100%; }
        .meta-box td { padding: 2px 4px; }
        .content {
            font-size: 9.5pt;
            color: #334155;
        }
        h1, h2, h3, h4 {
            color: #0f172a;
            margin-top: 18px;
            margin-bottom: 8px;
        }
        h1 { font-size: 14pt; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        h2 { font-size: 12pt; color: #0f766e; }
        h3 { font-size: 10.5pt; }
        p { margin: 6px 0; }
        ul, ol { margin: 6px 0; padding-left: 20px; }
        li { margin-bottom: 4px; }
        code {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f1f5f9;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 8.5pt;
        }
        pre {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px;
            border-radius: 4px;
            font-size: 8pt;
            overflow-x: auto;
        }
        table.doc-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 8.5pt;
        }
        table.doc-table th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            font-weight: bold;
        }
        table.doc-table td {
            border: 1px solid #e2e8f0;
            padding: 5px 8px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 10mm;
            border-top: 1px solid #cbd5e1;
            font-size: 8pt;
            color: #64748b;
            padding-top: 4px;
        }
        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="clinic-brand">DxClinic Clinical Management System</div>
        <div class="manual-title">{{ $manualTitle ?? 'Official Operation Manual' }}</div>
    </div>

    <div class="meta-box">
        <table>
            <tr>
                <td><strong>System:</strong> DxClinic Clinical Suite</td>
                <td><strong>Manual Type:</strong> {{ $manualType ?? 'Standard Operating Procedure' }}</td>
                <td><strong>Version:</strong> Revision 49+</td>
            </tr>
            <tr>
                <td><strong>Last Updated:</strong> {{ date('F Y') }}</td>
                <td><strong>Developer:</strong> Kundan Kumar (Nexora Solutions)</td>
                <td><strong>Website:</strong> https://nexorasolutionsind.co.in/</td>
            </tr>
        </table>
    </div>

    <div class="content">
        {!! $renderedContent !!}
    </div>

    <div class="footer">
        <table style="width: 100%;">
            <tr>
                <td>DxClinic Revision 49+ &bull; {{ $manualTitle ?? 'Operation Manual' }} &bull; Developer: Kundan Kumar &bull; https://nexorasolutionsind.co.in/</td>
                <td style="text-align: right;">Page <span class="page-number"></span></td>
            </tr>
        </table>
    </div>
</body>
</html>
