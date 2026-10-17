<?php
/**
 * Professional Manuals & FAQ HTML Generator (Revision 39)
 * Generates beautiful, styled, print/PDF-optimized HTML documents for all manuals, quick cards, and FAQ.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Str;

$outputDir = __DIR__ . '/../public/manuals';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$manuals = [
    'admin_manual' => [
        'title' => 'DxClinic Administrator & Clinical Practice Manual',
        'subtitle' => 'Comprehensive Practice Management, Multi-Doctor Capacity, Financial Controls & System Governance',
        'source' => __DIR__ . '/../knowledge/manuals/admin_manual.md',
        'role' => 'Administrator',
        'badge' => 'Admin'
    ],
    'admin_quick_card' => [
        'title' => 'DxClinic Administrator Quick Reference Card',
        'subtitle' => 'Daily Operational Protocols, Cash Reconciliation, Doctor Capacity & System Governance Checklist',
        'source' => __DIR__ . '/../knowledge/manuals/admin_quick_card.md',
        'role' => 'Administrator',
        'badge' => 'Quick Card'
    ],
    'doctor_manual' => [
        'title' => 'DxClinic Attending Doctor Clinical Manual',
        'subtitle' => 'OPD Patient Engagement, Prescription Generation, Diagnostic Ordering & Daily Workflow Protocol',
        'source' => __DIR__ . '/../knowledge/manuals/doctor_manual.md',
        'role' => 'Doctor',
        'badge' => 'Doctor'
    ],
    'doctor_quick_card' => [
        'title' => 'DxClinic Attending Doctor Quick Reference Card',
        'subtitle' => 'Rapid Consultation Protocol, Keyboard Shortcuts, Prescription Workflow & Token Engagement',
        'source' => __DIR__ . '/../knowledge/manuals/doctor_quick_card.md',
        'role' => 'Doctor',
        'badge' => 'Quick Card'
    ],
    'operator_manual' => [
        'title' => 'DxClinic Reception & Front Desk Operator Manual',
        'subtitle' => 'Patient Registration, Token Allocation, Arrival Queue Management, Cash Collection & Billing Protocols',
        'source' => __DIR__ . '/../knowledge/manuals/operator_manual.md',
        'role' => 'Operator',
        'badge' => 'Operator'
    ],
    'operator_quick_card' => [
        'title' => 'DxClinic Reception & Front Desk Operator Quick Reference Card',
        'subtitle' => 'Rapid Token Booking, Arrival Check-in, Keyboard Shortcuts & Daily Cash Counter Protocol',
        'source' => __DIR__ . '/../knowledge/manuals/operator_quick_card.md',
        'role' => 'Operator',
        'badge' => 'Quick Card'
    ],
    'superadmin_manual' => [
        'title' => 'DxClinic Superadmin Fleet Platform Governance Manual',
        'subtitle' => 'Tenant Provisioning, Multi-Tenant Licensing, Doctor Seat Entitlements, Demo Control & Security Auditing',
        'source' => __DIR__ . '/../knowledge/manuals/superadmin_manual.md',
        'role' => 'Superadmin',
        'badge' => 'Superadmin'
    ],
    'superadmin_quick_card' => [
        'title' => 'DxClinic Superadmin Fleet Quick Reference Card',
        'subtitle' => 'Tenant Provisioning, Doctor Seat Add-ons, Demo Pause/Resume & Emergency Maintenance Protocol',
        'source' => __DIR__ . '/../knowledge/manuals/superadmin_quick_card.md',
        'role' => 'Superadmin',
        'badge' => 'Quick Card'
    ],
    'faq' => [
        'title' => 'DxClinic Comprehensive FAQ Knowledge Base',
        'subtitle' => '136 Authoritative Clinical, Operational, Financial, Multi-Doctor & System Governance Answers',
        'source' => __DIR__ . '/../knowledge/faq/faq.md',
        'role' => 'All Staff & Patients',
        'badge' => 'FAQ (136 Q&A)'
    ],
];

function generateHtmlDocument(array $doc, array $allDocs, string $key): string {
    $content = file_exists($doc['source']) ? file_get_contents($doc['source']) : '# ' . $doc['title'];
    
    // Parse markdown to HTML
    $htmlContent = Str::markdown($content);

    // Style improvements: convert raw blockquotes to styled alert callouts
    $htmlContent = preg_replace('/<blockquote>\s*<p><strong>Note:?<\/strong>/i', '<blockquote class="alert-box alert-note"><p><strong>Note:</strong>', $htmlContent);
    $htmlContent = preg_replace('/<blockquote>\s*<p><strong>Warning:?<\/strong>/i', '<blockquote class="alert-box alert-warning"><p><strong>Warning:</strong>', $htmlContent);
    $htmlContent = preg_replace('/<blockquote>\s*<p><strong>Important:?<\/strong>/i', '<blockquote class="alert-box alert-important"><p><strong>Important:</strong>', $htmlContent);
    $htmlContent = preg_replace('/<blockquote>\s*<p><strong>Rule/i', '<blockquote class="alert-box alert-rule"><p><strong>Rule', $htmlContent);

    // Build Navigation Links for Toolbar
    $navOptions = '';
    foreach ($allDocs as $k => $d) {
        $selected = ($k === $key) ? 'selected' : '';
        $navOptions .= "<option value=\"{$k}.html\" {$selected}>{$d['badge']}: {$d['title']}</option>\n";
    }

    $timestamp = date('d M Y, H:i T');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$doc['title']} — DxClinic Documentation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --teal: #0d9488;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --border: #e2e8f0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            line-height: 1.6;
            font-size: 14.5px;
        }

        /* Top Action Bar (Screen Only) */
        .top-toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #0f172a;
            color: #ffffff;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            border-bottom: 1px solid #1e293b;
        }
        .toolbar-brand { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: #fff; }
        .toolbar-brand .logo {
            background: var(--teal);
            color: #fff;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.1rem;
        }
        .toolbar-brand .title { font-weight: 800; font-size: 1rem; letter-spacing: -0.02em; }
        .toolbar-actions { display: flex; align-items: center; gap: 0.75rem; }
        .btn-tool {
            background: #1e293b;
            color: #f1f5f9;
            border: 1px solid #334155;
            padding: 0.45rem 0.9rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s;
        }
        .btn-tool:hover { background: #334155; color: #fff; }
        .btn-tool.primary { background: var(--teal); border-color: var(--teal); color: #fff; }
        .btn-tool.primary:hover { background: #0f766e; }
        .doc-select {
            background: #1e293b;
            color: #f1f5f9;
            border: 1px solid #334155;
            padding: 0.45rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Container Layout */
        .container {
            max-width: 1040px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* Header Card */
        .doc-header {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 2.25rem 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        .doc-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--teal), var(--primary));
        }
        .badge-role {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.2rem 0.65rem;
            border-radius: 9999px;
            margin-bottom: 0.75rem;
            border: 1px solid #bae6fd;
        }
        .doc-title { font-size: 2rem; font-weight: 900; letter-spacing: -0.03em; color: #0f172a; line-height: 1.25; margin-bottom: 0.5rem; }
        .doc-sub { font-size: 1rem; color: var(--text-muted); font-weight: 500; }
        .doc-meta { margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 1.5rem; font-size: 0.8rem; color: var(--text-muted); }
        .doc-meta strong { color: var(--text-main); }

        /* Document Body & Typography */
        .doc-body {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 2.5rem 3rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        h1, h2, h3, h4 { color: #0f172a; font-weight: 800; letter-spacing: -0.02em; margin-top: 2rem; margin-bottom: 0.75rem; }
        h1 { font-size: 1.75rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; margin-top: 2.5rem; }
        h2 { font-size: 1.4rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.4rem; }
        h3 { font-size: 1.15rem; }
        h4 { font-size: 1rem; }
        p { margin-bottom: 1rem; color: #334155; }
        ul, ol { margin-bottom: 1.25rem; padding-left: 1.75rem; color: #334155; }
        li { margin-bottom: 0.4rem; }
        
        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            font-size: 0.875rem;
            border-radius: 0.5rem;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        th {
            background: #0f172a;
            color: #f8fafc;
            text-align: left;
            padding: 0.75rem 1rem;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            color: #334155;
            vertical-align: top;
        }
        tr:nth-child(even) td { background: #f8fafc; }
        tr:hover td { background: #f1f5f9; }

        /* Code & Pre */
        code {
            font-family: 'JetBrains Mono', monospace;
            background: #f1f5f9;
            color: #0f172a;
            padding: 0.2rem 0.4rem;
            border-radius: 0.3rem;
            font-size: 0.825em;
            font-weight: 600;
            border: 1px solid #e2e8f0;
        }
        pre {
            background: #0f172a;
            color: #f8fafc;
            padding: 1.25rem 1.5rem;
            border-radius: 0.75rem;
            overflow-x: auto;
            margin: 1.25rem 0;
            font-size: 0.85rem;
            line-height: 1.5;
        }
        pre code { background: transparent; color: inherit; border: none; padding: 0; font-weight: normal; }

        /* Callout / Alert Boxes */
        blockquote, .alert-box {
            margin: 1.5rem 0;
            padding: 1rem 1.25rem;
            border-left: 4px solid var(--teal);
            background: #f0fdfa;
            border-radius: 0 0.5rem 0.5rem 0;
            color: #134e4a;
        }
        blockquote p { margin: 0; color: inherit; }
        .alert-warning { border-left-color: #f59e0b; background: #fffbeb; color: #92400e; }
        .alert-note { border-left-color: var(--primary); background: #f0f9ff; color: #075985; }
        .alert-important { border-left-color: #ef4444; background: #fef2f2; color: #991b1b; }
        .alert-rule { border-left-color: #8b5cf6; background: #f5f3ff; color: #5b21b6; }

        /* Badges */
        kbd {
            font-family: 'JetBrains Mono', monospace;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            box-shadow: 0 2px 0 #94a3b8;
            color: #0f172a;
            padding: 0.15rem 0.45rem;
            border-radius: 0.3rem;
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* Footer */
        .doc-footer {
            margin-top: 3rem;
            padding: 2rem 0;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* PRINT / PDF FORMATTING (CSS @page & @media print) */
        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm 15mm 15mm 15mm;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 10pt;
                line-height: 1.45;
            }
            .no-print, .top-toolbar { display: none !important; }
            .container { max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .doc-header, .doc-body {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin-bottom: 1.5rem !important;
            }
            .doc-header::before { display: none; }
            .doc-title { font-size: 18pt !important; margin-bottom: 0.25rem !important; }
            .doc-sub { font-size: 11pt !important; }
            h1 { font-size: 14pt !important; margin-top: 1.5rem !important; page-break-after: avoid; }
            h2 { font-size: 12pt !important; margin-top: 1.25rem !important; page-break-after: avoid; }
            h3 { font-size: 11pt !important; margin-top: 1rem !important; page-break-after: avoid; }
            table { page-break-inside: avoid; font-size: 8.5pt !important; }
            th { background: #334155 !important; color: #fff !important; }
            pre { page-break-inside: avoid; background: #f8fafc !important; color: #000 !important; border: 1px solid #cbd5e1 !important; }
            blockquote { page-break-inside: avoid; }
            .page-break { page-break-before: always; }
            .avoid-break { page-break-inside: avoid; }
            a { text-decoration: none !important; color: #000 !important; }
            a[href^="http"]::after { content: " (" attr(href) ")"; font-size: 8pt; color: #64748b; }
        }
    </style>
</head>
<body>

    <!-- Sticky Toolbar (Screen only) -->
    <div class="top-toolbar no-print">
        <a href="/dashboard" class="toolbar-brand">
            <div class="logo">+</div>
            <div class="title">DxClinic Clinical Manuals</div>
        </a>
        <div class="toolbar-actions">
            <select class="doc-select" onchange="window.location.href=this.value">
                {$navOptions}
            </select>
            <button type="button" class="btn-tool primary" onclick="window.print()">
                <span>🖨 Print / Save as PDF</span>
            </button>
            <a href="/manuals/index.html" class="btn-tool">
                <span>📚 All Manuals Hub</span>
            </a>
            <a href="/dashboard" class="btn-tool">
                <span>&larr; Back to Dashboard</span>
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Header -->
        <header class="doc-header">
            <span class="badge-role">{$doc['role']} &bull; {$doc['badge']}</span>
            <h1 class="doc-title">{$doc['title']}</h1>
            <p class="doc-sub">{$doc['subtitle']}</p>
            <div class="doc-meta">
                <div><strong>Platform:</strong> DxClinic Practice Suite (Revision 39)</div>
                <div><strong>Standard:</strong> NABH / ISO-Compliant OPD Workflow</div>
                <div><strong>Published:</strong> {$timestamp}</div>
                <div><strong>Classification:</strong> Authoritative Clinical System Manual</div>
            </div>
        </header>

        <!-- Markdown Converted Document Content -->
        <main class="doc-body">
            {$htmlContent}
        </main>

        <!-- Footer -->
        <footer class="doc-footer">
            <p>&copy; 2026 Nexora Solutions &bull; DxClinic Platform Developed by Kundan Kumar.</p>
            <p>Confidential & Authoritative Clinical Management Documentation. All Rights Reserved.</p>
        </footer>
    </div>

</body>
</html>
HTML;
}

// Generate each standalone manual HTML
foreach ($manuals as $key => $doc) {
    echo "Generating {$key}.html ... ";
    $html = generateHtmlDocument($doc, $manuals, $key);
    file_put_contents("{$outputDir}/{$key}.html", $html);
    echo "DONE (" . strlen($html) . " bytes)\n";
}

// Generate Central Manuals Hub index.html
echo "Generating index.html (Central Hub) ... ";
$cardsHtml = '';
foreach ($manuals as $key => $doc) {
    $cardsHtml .= <<<CARD
        <a href="{$key}.html" class="hub-card">
            <div class="badge">{$doc['badge']}</div>
            <h3 class="title">{$doc['title']}</h3>
            <p class="desc">{$doc['subtitle']}</p>
            <span class="link">View Manual &rarr;</span>
        </a>
CARD;
}

$hubHtml = <<<HUB
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DxClinic User Manuals & Documentation Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #0f172a; color: #f8fafc; min-height: 100vh; padding: 2.5rem 1.5rem; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 3rem; }
        .logo { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: #0d9488; color: #fff; font-size: 1.75rem; font-weight: 900; border-radius: 12px; margin-bottom: 1rem; }
        .title { font-size: 2.25rem; font-weight: 900; letter-spacing: -0.03em; color: #ffffff; }
        .subtitle { font-size: 1.05rem; color: #94a3b8; margin-top: 0.5rem; max-width: 650px; margin-left: auto; margin-right: auto; }
        .actions-bar { display: flex; justify-content: center; gap: 1rem; margin-top: 1.5rem; }
        .btn-act { background: #1e293b; color: #f1f5f9; border: 1px solid #334155; padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-act:hover { background: #334155; }
        .btn-act.primary { background: #0d9488; border-color: #0d9488; color: #fff; }
        .btn-act.primary:hover { background: #0f766e; }

        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-top: 2.5rem; }
        .hub-card { background: #131d2e; border: 1px solid #1e293b; border-radius: 1rem; padding: 1.75rem; text-decoration: none; color: inherit; display: flex; flex-direction: column; transition: all 0.2s; position: relative; overflow: hidden; }
        .hub-card:hover { transform: translateY(-3px); border-color: #38bdf8; box-shadow: 0 10px 25px -5px rgba(56, 189, 248, 0.15); }
        .hub-card .badge { display: inline-block; background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); font-size: 0.75rem; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 9999px; text-transform: uppercase; width: fit-content; margin-bottom: 1rem; }
        .hub-card .title { font-size: 1.15rem; font-weight: 800; color: #ffffff; margin-bottom: 0.5rem; line-height: 1.35; }
        .hub-card .desc { font-size: 0.85rem; color: #94a3b8; line-height: 1.5; flex: 1; margin-bottom: 1.25rem; }
        .hub-card .link { font-size: 0.85rem; font-weight: 700; color: #38bdf8; display: inline-flex; align-items: center; gap: 0.3rem; }

        .footer { text-align: center; margin-top: 4rem; padding-top: 2rem; border-top: 1px solid #1e293b; color: #64748b; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo">+</div>
            <h1 class="title">DxClinic Documentation & Manuals Hub</h1>
            <p class="subtitle">Complete professional role-based user manuals, 1-page quick cards, and comprehensive searchable FAQ with print and PDF optimization.</p>
            <div class="actions-bar">
                <a href="/dashboard" class="btn-act primary">&larr; Back to Clinic Dashboard</a>
                <a href="/manuals/user_manual.html" class="btn-act">📖 Master Consolidated Manual</a>
                <a href="/manuals/faq.html" class="btn-act">❓ Search 136 FAQ</a>
            </div>
        </header>

        <div class="grid">
            {$cardsHtml}
        </div>

        <footer class="footer">
            <p>&copy; 2026 Nexora Solutions &bull; DxClinic Platform Developed by Kundan Kumar.</p>
            <p>Revision 39 Operational UX Hardening &amp; Authoritative Documentation.</p>
        </footer>
    </div>
</body>
</html>
HUB;
file_put_contents("{$outputDir}/index.html", $hubHtml);
echo "DONE (" . strlen($hubHtml) . " bytes)\n";

// Also copy admin_manual.html to user_manual.html so /manuals/user_manual.html works as default master manual
copy("{$outputDir}/admin_manual.html", "{$outputDir}/user_manual.html");
echo "Copied user_manual.html\n";

echo "All professional manuals generated successfully in public/manuals/\n";
