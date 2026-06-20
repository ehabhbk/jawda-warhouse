<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تقرير شامل</title>
    <style>
        @page { margin: 15mm 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1e293b; line-height: 1.6; }
        .header { text-align: center; padding-bottom: 12px; border-bottom: 3px solid #2563eb; margin-bottom: 15px; }
        .header h1 { font-size: 18px; color: #1e3a5f; margin-bottom: 4px; }
        .header p { font-size: 10px; color: #64748b; }
        .header .date-range { font-size: 9px; color: #2563eb; margin-top: 4px; }
        .section { margin-bottom: 18px; }
        .section-title { font-size: 12px; font-weight: bold; color: #1e3a5f; padding: 6px 10px; background: #eff6ff; border-right: 4px solid #2563eb; margin-bottom: 8px; }
        .summary-grid { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
        .summary-card { flex: 1; min-width: 100px; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 6px; text-align: center; }
        .summary-card .num { font-size: 14px; font-weight: bold; color: #2563eb; }
        .summary-card .lbl { font-size: 7px; color: #64748b; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 8px; }
        th { background: #f1f5f9; padding: 5px 6px; text-align: right; font-size: 8px; border-bottom: 2px solid #cbd5e1; }
        td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) td { background: #f8fafc; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 7px; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-rose { background: #ffe4e6; color: #9f1239; }
        .footer { text-align: center; font-size: 7px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: 20px; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-muted { color: #94a3b8; }
        .mb-1 { margin-bottom: 4px; }
        .mt-1 { margin-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>تقرير المخازن الشامل</h1>
        <p>نظام إدارة المخازن</p>
        <div class="date-range">
            الفترة: {{ $fromDate ?? 'البداية' }} → {{ $toDate ?? 'النهاية' }}
            &nbsp;|&nbsp; تاريخ التقرير: {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>

    <div class="section">
        <div class="section-title">ملخص عام</div>
        <div class="summary-grid">
            <div class="summary-card"><div class="num">{{ number_format($totalItems) }}</div><div class="lbl">إجمالي الأصناف</div></div>
            <div class="summary-card"><div class="num">{{ number_format($totalWarehouses) }}</div><div class="lbl">المخازن</div></div>
            <div class="summary-card"><div class="num">{{ number_format($totalPurchases) }}</div><div class="lbl">المشتريات</div></div>
            <div class="summary-card"><div class="num">{{ number_format($totalOrders) }}</div><div class="lbl">الطلبات</div></div>
            <div class="summary-card"><div class="num">{{ number_format($totalSales) }}</div><div class="lbl">المبيعات</div></div>
            <div class="summary-card"><div class="num">{{ number_format($totalSuppliers) }}</div><div class="lbl">الموردين</div></div>
            <div class="summary-card"><div class="num">{{ number_format($lowStockItems) }}</div><div class="lbl">منخفض المخزون</div></div>
            <div class="summary-card"><div class="num">{{ number_format($expiredItems) }}</div><div class="lbl">منتهي الصلاحية</div></div>
        </div>
        <div style="display:flex;gap:10px;margin-top:6px;">
            <div style="flex:1;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;">
                <p style="font-size:7px;color:#64748b;">إجمالي المشتريات</p>
                <p style="font-size:13px;font-weight:bold;color:#10b981;">{{ number_format($totalPurchaseAmount, 2) }} ر.س</p>
            </div>
            <div style="flex:1;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;">
                <p style="font-size:7px;color:#64748b;">إجمالي المبيعات</p>
                <p style="font-size:13px;font-weight:bold;color:#8b5cf6;">{{ number_format($totalSaleAmount, 2) }} ر.س</p>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">المشتريات ({{ $fromDate }} → {{ $toDate }})</div>
        @if(count($purchases) > 0)
            <table>
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>المورد</th>
                        <th>التاريخ</th>
                        <th>عدد الأصناف</th>
                        <th class="text-center">الحالة</th>
                        <th class="text-left">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchases as $p)
                    <tr>
                        <td>{{ $p->invoice_number }}</td>
                        <td>{{ $p->supplier?->name ?? '-' }}</td>
                        <td>{{ optional($p->purchase_date)->format('Y-m-d') }}</td>
                        <td>{{ $p->items_count }}</td>
                        <td class="text-center"><span class="badge {{ $p->status === 'completed' ? 'badge-green' : ($p->status === 'cancelled' ? 'badge-rose' : 'badge-amber') }}">{{ $p->status === 'completed' ? 'مكتملة' : ($p->status === 'cancelled' ? 'ملغية' : 'معلقة') }}</span></td>
                        <td class="text-left">{{ number_format($p->grand_total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted" style="padding:8px;">لا توجد مشتريات في هذه الفترة</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">الطلبات ({{ $fromDate }} → {{ $toDate }})</div>
        @if(count($orders) > 0)
            <table>
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>مقدم الطلب</th>
                        <th>المخزن</th>
                        <th>الأصناف</th>
                        <th class="text-center">الحالة</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $o)
                    <tr>
                        <td>{{ $o->order_number }}</td>
                        <td>{{ $o->user?->name ?? '-' }}</td>
                        <td>{{ $o->warehouse?->name ?? '-' }}</td>
                        <td>{{ $o->items_count }} صنف</td>
                        <td class="text-center"><span class="badge {{ $o->status === 'completed' ? 'badge-green' : ($o->status === 'rejected' ? 'badge-rose' : ($o->status === 'approved' ? 'badge-blue' : 'badge-amber')) }}">
                            {{ $o->status === 'pending' ? 'معلق' : ($o->status === 'approved' ? 'معتمد' : ($o->status === 'rejected' ? 'مرفوض' : 'مكتمل')) }}
                        </span></td>
                        <td>{{ optional($o->created_at)->format('Y-m-d') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted" style="padding:8px;">لا توجد طلبات في هذه الفترة</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">مبيعات نقاط البيع ({{ $fromDate }} → {{ $toDate }})</div>
        @if(count($sales) > 0)
            <table>
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>المستخدم</th>
                        <th>التاريخ</th>
                        <th>عدد الأصناف</th>
                        <th class="text-left">الإجمالي</th>
                        <th class="text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sales as $s)
                    <tr>
                        <td>{{ $s->invoice_number }}</td>
                        <td>{{ $s->user?->name ?? '-' }}</td>
                        <td>{{ optional($s->created_at)->format('Y-m-d H:i') }}</td>
                        <td>{{ $s->items_count }}</td>
                        <td class="text-left">{{ number_format($s->grand_total, 2) }}</td>
                        <td class="text-center"><span class="badge {{ $s->status === 'completed' ? 'badge-green' : 'badge-rose' }}">{{ $s->status === 'completed' ? 'مكتملة' : 'ملغية' }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted" style="padding:8px;">لا توجد مبيعات في هذه الفترة</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">المخزون حسب المخازن</div>
        @if(count($inventory) > 0)
            <table>
                <thead>
                    <tr>
                        <th>المخزن</th>
                        <th class="text-center">الأصناف</th>
                        <th class="text-center">إجمالي الكمية</th>
                        <th class="text-left">إجمالي القيمة</th>
                        <th class="text-center">منخفض المخزون</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($inventory as $w)
                    <tr>
                        <td>{{ $w['name'] }}</td>
                        <td class="text-center">{{ $w['items_count'] }}</td>
                        <td class="text-center">{{ number_format($w['total_quantity']) }}</td>
                        <td class="text-left">{{ number_format($w['total_value'], 2) }}</td>
                        <td class="text-center">{{ $w['low_stock_count'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted" style="padding:8px;">لا توجد مخازن</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">حركات المخزن الأخيرة</div>
        @if(count($movements) > 0)
            <table>
                <thead>
                    <tr>
                        <th>الصنف</th>
                        <th class="text-center">النوع</th>
                        <th class="text-center">الكمية</th>
                        <th>المستخدم</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($movements as $m)
                    <tr>
                        <td>{{ $m->item?->name ?? '-' }}</td>
                        <td class="text-center"><span class="badge {{ $m->type === 'in' ? 'badge-green' : 'badge-rose' }}">{{ $m->type === 'in' ? 'إضافة' : 'صرف' }}</span></td>
                        <td class="text-center">{{ number_format($m->quantity) }}</td>
                        <td>{{ $m->user?->name ?? '-' }}</td>
                        <td>{{ optional($m->created_at)->format('Y-m-d H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted" style="padding:8px;">لا توجد حركات</p>
        @endif
    </div>

    <div class="footer">
        تم إنشاء هذا التقرير بواسطة نظام إدارة المخازن - {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>