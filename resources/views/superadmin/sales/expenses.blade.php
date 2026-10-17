<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Expenses — DxClinic Superadmin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #0b0f19; color: #f1f5f9; min-height: 100vh; padding: 1.5rem; }
        .topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #1e293b; padding-bottom: 1.25rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .brand { display: flex; align-items: center; gap: 0.75rem; }
        .brand h1 { font-size: 1.4rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
        .brand .badge { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; }
        .topbar-actions { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
        .btn-link { background: #1e293b; border: 1px solid #334155; color: #cbd5e1; padding: 0.6rem 0.9rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; }
        .btn-link:hover { background: #334155; color: #ffffff; }
        .btn-link.active { border-color: #f87171; color: #f87171; background: rgba(239, 68, 68, 0.1); }
        .btn-logout { background: #1e293b; border: 1px solid #334155; color: #cbd5e1; padding: 0.6rem 0.9rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
        .btn-logout:hover { background: #334155; color: #ffffff; }

        /* Accounting isolation banner */
        .banner { background: rgba(15, 23, 42, 0.8); border: 1px solid #1e293b; border-left: 4px solid #ef4444; border-radius: 0.5rem; padding: 1rem 1.25rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: #94a3b8; }

        .layout-grid { display: grid; grid-template-columns: 340px 1fr; gap: 1.5rem; align-items: start; }
        @media (max-width: 900px) { .layout-grid { grid-template-columns: 1fr; } }

        .panel { background: #131d2e; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.5rem; }
        .panel-title { font-size: 1.1rem; font-weight: 800; color: #ffffff; margin-bottom: 1.25rem; border-bottom: 1px solid #1e293b; padding-bottom: 0.75rem; }

        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem; }
        .form-input, .form-select, .form-textarea { width: 100%; background: #0b0f19; border: 1px solid #27354a; border-radius: 0.4rem; padding: 0.6rem 0.75rem; color: #f8fafc; font-size: 0.85rem; }
        .btn-submit { width: 100%; background: #ef4444; color: #ffffff; border: none; border-radius: 0.5rem; padding: 0.75rem; font-weight: 700; font-size: 0.9rem; cursor: pointer; margin-top: 0.5rem; }
        .btn-submit:hover { background: #dc2626; }

        .total-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; }
        .total-val { font-size: 1.5rem; font-weight: 800; color: #f87171; font-family: 'JetBrains Mono', monospace; }

        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem; }
        th { background: #0f172a; padding: 0.85rem 1rem; color: #94a3b8; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #1e293b; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #1e293b; color: #cbd5e1; vertical-align: middle; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }
        .money { font-family: 'JetBrains Mono', monospace; font-weight: 700; }

        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #34d399; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="brand">
            <h1>DxClinic Fleet</h1>
            <span class="badge">Platform Overhead Expenses</span>
        </div>
        <div class="topbar-actions">
            <a href="{{ route('superadmin.dashboard') }}" class="btn-link">🏥 Fleet Dashboard</a>
            <a href="{{ route('superadmin.sales') }}" class="btn-link">📊 Sales</a>
            <a href="{{ route('superadmin.invoices') }}" class="btn-link">🧾 Invoices</a>
            <a href="{{ route('superadmin.collections') }}" class="btn-link">💰 Collections</a>
            <a href="{{ route('superadmin.expenses') }}" class="btn-link active">📉 Expenses</a>
            <form action="{{ route('superadmin.logout') }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="btn-logout">Sign Out</button>
            </form>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- Accounting Separation Banner (Specification 14 & 17) -->
    <div class="banner">
        <strong style="color: #ffffff;">Provider-Level Operating Expenses Only</strong>
        <p style="margin-top: 0.25rem;">
            These expenses represent platform operations (hosting, SMS gateway, domain/SSL, engineering salaries). 
            Strictly isolated from clinic-level tenant accounts (no clinic_id).
        </p>
    </div>

    <div class="layout-grid">
        <!-- New Expense Form -->
        <div class="panel">
            <div class="panel-title">Record Platform Expense</div>
            <form action="{{ route('superadmin.expenses.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select name="category" required class="form-select">
                        @foreach($categories as $key => $name)
                            <option value="{{ $key }}" {{ old('category') === $key ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Description *</label>
                    <input type="text" name="description" required value="{{ old('description') }}" placeholder="e.g. AWS Cloud Cluster EC2 & RDS" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Amount (₹) *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}" placeholder="0.00" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Expense Date *</label>
                    <input type="date" name="expense_date" required value="{{ old('expense_date', date('Y-m-d')) }}" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method *</label>
                    <select name="payment_method" required class="form-select">
                        <option value="BANK_TRANSFER">Bank Transfer / NEFT</option>
                        <option value="CREDIT_CARD">Corporate Credit Card</option>
                        <option value="UPI">UPI</option>
                        <option value="CASH">Cash</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Reference / Bill #</label>
                    <input type="text" name="reference" value="{{ old('reference') }}" placeholder="e.g. INV-AWS-928129" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Optional notes..." class="form-textarea">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="btn-submit">Record Expense</button>
            </form>
        </div>

        <!-- Expenses List & Totals -->
        <div>
            <div class="total-card">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Total Filtered Expenses</div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.2rem;">Deducted directly from Net Sales to compute Operating Profit</div>
                </div>
                <div class="total-val">₹{{ number_format($totalExpenses, 2) }}</div>
            </div>

            <div class="panel" style="padding: 0; overflow: hidden;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Method & Ref</th>
                            <th style="text-align: right;">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expenses as $exp)
                            <tr>
                                <td>{{ $exp->expense_date ? $exp->expense_date->format('d M Y') : '-' }}</td>
                                <td>
                                    <span style="font-size: 0.75rem; color: #cbd5e1; font-weight: 600;">
                                        {{ $categories[$exp->category] ?? $exp->category }}
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: #ffffff;">{{ $exp->description }}</strong>
                                    @if($exp->notes)
                                        <div style="font-size: 0.75rem; color: #64748b;">{{ $exp->notes }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div style="font-size: 0.8rem;">{{ $exp->payment_method }}</div>
                                    @if($exp->reference)
                                        <div style="font-size: 0.72rem; color: #64748b; font-family: 'JetBrains Mono', monospace;">{{ $exp->reference }}</div>
                                    @endif
                                </td>
                                <td style="text-align: right; color: #f87171;" class="money">
                                    ₹{{ number_format($exp->amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; color: #64748b; padding: 2.5rem;">
                                    No platform expenses recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 1rem;">
                {{ $expenses->links() }}
            </div>
        </div>
    </div>
</body>
</html>
