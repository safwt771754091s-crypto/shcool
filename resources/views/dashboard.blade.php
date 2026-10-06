@extends('layouts.master')

@section('style')
<style>
    .hq-dashboard{font-family:"Tajawal","Cairo",Arial,sans-serif;color:#0f172a;direction:rtl}
    .hq-dashboard *{box-sizing:border-box}
    .hq-hero{
        position:relative;overflow:hidden;border-radius:24px;padding:30px 34px;margin-bottom:22px;
        color:#fff;background:linear-gradient(135deg,#0b4fc9 0%,#1267e8 48%,#063b9b 100%);
        box-shadow:0 18px 45px rgba(15,72,160,.22)
    }
    .hq-hero:before,.hq-hero:after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.07)}
    .hq-hero:before{width:330px;height:330px;left:-90px;top:-170px}
    .hq-hero:after{width:260px;height:260px;right:-80px;bottom:-170px}
    .hq-hero-inner{position:relative;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:25px}
    .hq-brand-mark{width:92px;height:92px;border-radius:24px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);display:flex;align-items:center;justify-content:center;font-size:46px;flex:0 0 auto}
    .hq-hero h1{font-size:30px;font-weight:800;margin:0 0 10px}
    .hq-hero p{font-size:16px;line-height:1.9;margin:0;color:rgba(255,255,255,.9)}
    .hq-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}
    .hq-action{display:inline-flex;align-items:center;gap:8px;padding:11px 17px;border-radius:13px;text-decoration:none!important;font-weight:700;background:#fbbf24;color:#13233f!important;box-shadow:0 8px 18px rgba(0,0,0,.13)}
    .hq-action.secondary{background:rgba(255,255,255,.12);color:#fff!important;border:1px solid rgba(255,255,255,.25)}
    .hq-section-title{display:flex;align-items:center;justify-content:space-between;margin:25px 2px 12px}
    .hq-section-title h2{font-size:20px;font-weight:800;margin:0;color:#0f3d87}
    .hq-section-title span{font-size:12px;color:#64748b}
    .hq-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px}
    .hq-stat{position:relative;overflow:hidden;min-height:135px;border-radius:19px;padding:21px;color:#fff;box-shadow:0 12px 28px rgba(15,23,42,.10);transition:.2s}
    .hq-stat:hover{transform:translateY(-3px)}
    .hq-stat.blue{background:linear-gradient(135deg,#0d6efd,#1357bd)}
    .hq-stat.green{background:linear-gradient(135deg,#13b86b,#079451)}
    .hq-stat.orange{background:linear-gradient(135deg,#ffab17,#ed7b00)}
    .hq-stat.red{background:linear-gradient(135deg,#ff3f5b,#dc1839)}
    .hq-stat.purple{background:linear-gradient(135deg,#7356e8,#4c32b7)}
    .hq-stat.cyan{background:linear-gradient(135deg,#0ba7c7,#087d9c)}
    .hq-stat .ico{width:48px;height:48px;border-radius:15px;background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center;font-size:23px;float:right;margin-left:13px}
    .hq-stat small{display:block;font-size:13px;font-weight:700;opacity:.88;margin-top:2px}
    .hq-stat strong{display:block;font-size:31px;font-weight:800;margin-top:8px}
    .hq-stat em{font-style:normal;font-size:12px;opacity:.88}
    .hq-stat:after{content:"";position:absolute;width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,.07);left:-45px;bottom:-75px}
    .hq-grid{display:grid;grid-template-columns:1.05fr 1fr 1.35fr;gap:17px;align-items:stretch}
    .hq-card{background:#fff;border:1px solid #e8eef7;border-radius:20px;padding:20px;box-shadow:0 10px 28px rgba(15,23,42,.06)}
    .hq-card h3{font-size:18px;font-weight:800;margin:0 0 17px;color:#123f88}
    .hq-card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:15px}
    .hq-card-head h3{margin:0}
    .hq-link{font-size:12px;font-weight:700;color:#1267e8;text-decoration:none!important}
    .hq-quick{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
    .hq-quick a{min-height:92px;border:1px solid #e4ebf5;border-radius:15px;padding:12px 8px;text-align:center;text-decoration:none!important;color:#173d7b;background:#fbfdff;transition:.18s}
    .hq-quick a:hover{background:#f0f6ff;border-color:#b9d3ff;transform:translateY(-2px)}
    .hq-quick i{display:block;font-size:25px;margin-bottom:7px;color:#1267e8}
    .hq-quick span{font-size:12px;font-weight:700}
    .hq-attendance{background:linear-gradient(145deg,#073f9e,#0d62d9);color:#fff;border:0}
    .hq-attendance h3{color:#fff}
    .hq-att-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .hq-att-box{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:15px;text-align:center}
    .hq-att-box strong{font-size:30px;display:block;font-weight:800}
    .hq-att-box span{font-size:12px;opacity:.9}
    .hq-att-detail{margin-top:13px;display:flex;justify-content:space-between;align-items:center;padding:11px 13px;border:1px solid rgba(255,255,255,.2);border-radius:12px;color:#fff;text-decoration:none!important;font-size:12px;font-weight:700}
    .hq-chart{height:230px;display:flex;align-items:flex-end;gap:9px;padding:8px 4px 30px;border-bottom:1px solid #e7edf5;position:relative}
    .hq-chart-col{flex:1;height:100%;display:flex;align-items:flex-end;justify-content:center;gap:3px;position:relative}
    .hq-bar{width:13px;border-radius:7px 7px 2px 2px;min-height:3px}
    .hq-bar.income{background:#13b87a}
    .hq-bar.expense{background:#ff4560}
    .hq-chart-label{position:absolute;bottom:-27px;font-size:10px;color:#64748b;white-space:nowrap}
    .hq-legend{display:flex;gap:16px;margin-top:12px;font-size:11px;color:#64748b}
    .hq-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-left:5px}
    .hq-finance{display:grid;grid-template-columns:1fr 1fr 1fr;gap:9px;margin-top:16px}
    .hq-fin{background:#f7faff;border-radius:13px;padding:12px;text-align:center}
    .hq-fin strong{display:block;font-size:17px;color:#123f88}
    .hq-fin span{font-size:10px;color:#64748b}
    .hq-att-list{margin-top:15px}
    .hq-att-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.12);font-size:11px}
    .hq-att-row:last-child{border-bottom:0}
    .hq-pill{padding:4px 8px;border-radius:999px;background:rgba(255,255,255,.12)}
    .hq-notice{margin-top:18px;border-radius:16px;padding:15px 17px;background:#eef6ff;border:1px solid #d5e7ff;color:#16417f;display:flex;align-items:center;gap:10px}
    .hq-notice i{font-size:21px}
    .hq-filter{display:flex;align-items:end;gap:10px;background:#fff;border:1px solid #e8eef7;padding:13px;border-radius:16px;box-shadow:0 8px 20px rgba(15,23,42,.04);margin-bottom:17px}
    .hq-filter label{display:block;font-size:11px;color:#64748b;margin-bottom:5px;font-weight:700}
    .hq-filter select,.hq-filter input{height:38px;border:1px solid #dbe4f0;border-radius:10px;padding:0 10px;background:#fff;min-width:145px}
    .hq-filter button{height:38px;border:0;border-radius:10px;background:#1267e8;color:#fff;padding:0 18px;font-weight:700}
    @media(max-width:1100px){.hq-stats{grid-template-columns:repeat(2,1fr)}.hq-grid{grid-template-columns:1fr 1fr}.hq-grid .hq-card:last-child{grid-column:1/-1}}
    @media(max-width:760px){.hq-hero-inner{flex-direction:column;align-items:flex-start}.hq-brand-mark{display:none}.hq-hero h1{font-size:24px}.hq-stats{grid-template-columns:1fr 1fr}.hq-grid{grid-template-columns:1fr}.hq-grid .hq-card:last-child{grid-column:auto}.hq-quick{grid-template-columns:repeat(2,1fr)}.hq-filter{flex-wrap:wrap}.hq-filter>div{flex:1;min-width:130px}.hq-filter button{width:100%}}
    @media(max-width:480px){.hq-stats{grid-template-columns:1fr}.hq-quick{grid-template-columns:repeat(2,1fr)}}
</style>
@stop

@section('content')
@php
    $incomeKeys = isset($incomes['key']) ? $incomes['key'] : [];
    $incomeVals = isset($incomes['value']) ? $incomes['value'] : [];
    $expenseKeys = isset($expences['key']) ? $expences['key'] : [];
    $expenseVals = isset($expences['value']) ? $expences['value'] : [];
    $chartCount = max(count($incomeKeys), count($expenseKeys));
    $chartMax = 1;
    foreach($incomeVals as $v){ $chartMax = max($chartMax, (float)$v); }
    foreach($expenseVals as $v){ $chartMax = max($chartMax, (float)$v); }
    $currentMonth = $month_n ?? now()->format('F');
    $paid = (float)($ourallpaid ?? 0);
    $unpaid = (float)($ourallunpaid ?? 0);
    $feePaid = isset($fee_check_status) ? (float)$fee_check_status->paiTotal : 0;
    $feeDue = isset($fee_check_status) ? (float)$fee_check_status->dueamount : 0;
    $balanceValue = (float)($balance ?? 0);
    $attendancePresent = 0;
    $attendanceAbsent = (int)($total['totalabsent'] ?? 0);
    $attendanceLate = (int)($total['totallate'] ?? 0);
    foreach(($attendances_b ?? []) as $a){ $attendancePresent += (int)($a['present'] ?? 0); }
@endphp

<div class="hq-dashboard">
    @if(Session::get('accessdined'))
        <div class="alert alert-danger"><strong>تعذر تنفيذ العملية:</strong> {{ Session::get('accessdined') }}</div>
    @endif
    @if(Session::get('success'))
        <div class="alert alert-success"><strong>تمت العملية بنجاح:</strong> {{ Session::get('success') }}</div>
    @endif

    <div class="hq-hero">
        <div class="hq-hero-inner">
            <div>
                <div style="font-size:12px;font-weight:700;opacity:.8;margin-bottom:8px">منصة الإدارة التعليمية المتكاملة</div>
                <h1>مرحباً بك في حقيبة المعلم الرقمية المتميزة 👑</h1>
                <p>لوحة تشغيل موحدة لإدارة المدرسة والطلاب والمعلمين والحضور والاختبارات والرسوم والمكتبة والمحاسبة من مكان واحد.</p>
                <div class="hq-actions">
                    <a class="hq-action" href="{{ url('/student/create') }}"><i class="fas fa-user-plus"></i> إضافة طالب</a>
                    <a class="hq-action" href="{{ url('/teacher/create') }}"><i class="fas fa-chalkboard-teacher"></i> إضافة مدرس</a>
                    <a class="hq-action" href="{{ url('/class/create') }}"><i class="fas fa-layer-group"></i> إنشاء فصل</a>
                    <a class="hq-action" href="{{ url('/subject/create') }}"><i class="fas fa-book"></i> إضافة مادة</a>
                </div>
            </div>
            <div class="hq-brand-mark"><i class="fas fa-graduation-cap"></i></div>
        </div>
    </div>

    <form class="hq-filter" method="get" action="{{ url('/dashboard') }}">
        <div>
            <label for="hq-month">الشهر</label>
            <select id="hq-month" name="month">
                @foreach([1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'] as $m=>$name)
                    <option value="{{ $m }}" {{ (int)$month === $m ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="hq-year">السنة</label>
            <input id="hq-year" type="number" name="year" value="{{ request('year', $year1) }}" min="2000" max="2100">
        </div>
        <button type="submit"><i class="fas fa-filter"></i> تحديث المؤشرات</button>
    </form>

    <div class="hq-section-title">
        <h2>نظرة سريعة على المدرسة</h2>
        <span>بيانات حقيقية من قاعدة بيانات النظام</span>
    </div>

    <div class="hq-stats">
        <a class="hq-stat blue" href="{{ url('/student/list') }}" style="text-decoration:none;color:#fff">
            <span class="ico"><i class="fas fa-users"></i></span>
            <small>إجمالي الطلاب</small><strong>{{ number_format($total['student'] ?? 0) }}</strong><em>طلاب مسجلون وفعّالون</em>
        </a>
        <a class="hq-stat orange" href="{{ url('/teacher/list') }}" style="text-decoration:none;color:#fff">
            <span class="ico"><i class="fas fa-chalkboard-teacher"></i></span>
            <small>إجمالي المدرسين</small><strong>{{ number_format($total['teacher'] ?? 0) }}</strong><em>مدرس ومدرسة</em>
        </a>
        <a class="hq-stat green" href="{{ url('/subject/list') }}" style="text-decoration:none;color:#fff">
            <span class="ico"><i class="fas fa-book-open"></i></span>
            <small>المواد الدراسية</small><strong>{{ number_format($total['subject'] ?? 0) }}</strong><em>مادة مرتبطة بالنظام</em>
        </a>
        <a class="hq-stat red" href="{{ url('/class/list') }}" style="text-decoration:none;color:#fff">
            <span class="ico"><i class="fas fa-school"></i></span>
            <small>الصفوف</small><strong>{{ number_format($total['class'] ?? 0) }}</strong><em>فصل دراسي</em>
        </a>
        <a class="hq-stat purple" href="{{ url('/attendance_detail?action=absent') }}" style="text-decoration:none;color:#fff">
            <span class="ico"><i class="fas fa-user-times"></i></span>
            <small>الغياب اليوم</small><strong>{{ number_format($attendanceAbsent) }}</strong><em>طالب غائب</em>
        </a>
        <a class="hq-stat cyan" href="{{ url('/attendance_detail?action=late') }}" style="text-decoration:none;color:#fff">
            <span class="ico"><i class="fas fa-clock"></i></span>
            <small>التأخر اليوم</small><strong>{{ number_format($attendanceLate) }}</strong><em>حالة تأخر</em>
        </a>
        @if(Auth::user()->group == 'Admin')
            <a class="hq-stat green" href="{{ url('/fee_detail?action=paid') }}" style="text-decoration:none;color:#fff">
                <span class="ico"><i class="fas fa-money-check-alt"></i></span>
                <small>الرسوم المحصلة</small><strong>{{ number_format($paid) }}</strong><em>عمليات مدفوعة</em>
            </a>
            <a class="hq-stat red" href="{{ url('/fee_detail?action=unpaid') }}" style="text-decoration:none;color:#fff">
                <span class="ico"><i class="fas fa-file-invoice-dollar"></i></span>
                <small>الرسوم غير المحصلة</small><strong>{{ number_format($unpaid) }}</strong><em>عمليات تحتاج متابعة</em>
            </a>
        @endif
    </div>

    <div class="hq-section-title">
        <h2>الوصول السريع والخدمات الأساسية</h2>
        <span>كل زر يفتح خدمة النظام الفعلية</span>
    </div>

    <div class="hq-grid">
        <div class="hq-card">
            <div class="hq-card-head"><h3><i class="fas fa-bolt" style="color:#f59e0b"></i> الوصول السريع</h3><a class="hq-link" href="{{ url('/dashboard') }}">الرئيسية</a></div>
            <div class="hq-quick">
                <a href="{{ url('/student/list') }}"><i class="fas fa-users"></i><span>الطلاب</span></a>
                <a href="{{ url('/teacher/list') }}"><i class="fas fa-chalkboard-teacher"></i><span>المدرسون</span></a>
                <a href="{{ url('/class/list') }}"><i class="fas fa-school"></i><span>الفصول</span></a>
                <a href="{{ url('/subject/list') }}"><i class="fas fa-book"></i><span>المواد</span></a>
                <a href="{{ url('/attendance/list') }}"><i class="fas fa-calendar-check"></i><span>الحضور</span></a>
                <a href="{{ url('/mark/list') }}"><i class="fas fa-clipboard-list"></i><span>الدرجات</span></a>
                <a href="{{ url('/accounting') }}"><i class="fas fa-coins"></i><span>المحاسبة</span></a>
                <a href="{{ url('/library/view') }}"><i class="fas fa-book-reader"></i><span>المكتبة</span></a>
                <a href="{{ url('/message') }}"><i class="fas fa-comments"></i><span>التواصل</span></a>
            </div>
        </div>

        <div class="hq-card hq-attendance">
            <div class="hq-card-head"><h3><i class="fas fa-chart-pie"></i> ملخص الحضور اليومي</h3><span style="font-size:11px;opacity:.8">{{ $currentMonth }}</span></div>
            <div class="hq-att-grid">
                <div class="hq-att-box"><strong>{{ number_format($attendancePresent) }}</strong><span>حاضر</span></div>
                <div class="hq-att-box"><strong>{{ number_format($attendanceAbsent) }}</strong><span>غائب</span></div>
                <div class="hq-att-box"><strong>{{ number_format($attendanceLate) }}</strong><span>متأخر</span></div>
                <div class="hq-att-box"><strong>{{ number_format($total['attendance'] ?? 0) }}</strong><span>أيام حضور مسجلة</span></div>
            </div>
            <a class="hq-att-detail" href="{{ url('/attendance/monthly-report') }}"><span>عرض تقرير الحضور الشهري</span><i class="fas fa-arrow-left"></i></a>
            <div class="hq-att-list">
                @foreach(array_slice($attendances_b ?? [],0,5) as $row)
                    <div class="hq-att-row">
                        <span>{{ $row['class'] ?? '—' }}</span>
                        <span class="hq-pill">حاضر {{ (int)($row['present'] ?? 0) }} · غائب {{ (int)($row['absent'] ?? 0) }}</span>
                    </div>
                @endforeach
                @if(count($attendances_b ?? []) === 0)<div style="opacity:.8;font-size:12px;text-align:center;padding:18px 0">لا توجد بيانات حضور مسجلة لليوم.</div>@endif
            </div>
        </div>

        <div class="hq-card">
            <div class="hq-card-head"><h3><i class="fas fa-chart-column" style="color:#1267e8"></i> الإيرادات والمصروفات</h3><a class="hq-link" href="{{ url('/accounting/report') }}">التقارير المالية</a></div>
            <div class="hq-chart">
                @for($i=0; $i<$chartCount; $i++)
                    @php
                        $iv = (float)($incomeVals[$i] ?? 0);
                        $ev = (float)($expenseVals[$i] ?? 0);
                        $label = $incomeKeys[$i] ?? ($expenseKeys[$i] ?? '');
                        $incomeHeight = max(3, min(100, ($iv / $chartMax) * 100));
                        $expenseHeight = max(3, min(100, ($ev / $chartMax) * 100));
                    @endphp
                    <div class="hq-chart-col">
                        <div class="hq-bar income" style="height:{{ $incomeHeight }}%"></div>
                        <div class="hq-bar expense" style="height:{{ $expenseHeight }}%"></div>
                        <span class="hq-chart-label">{{ $label }}</span>
                    </div>
                @endfor
                @if($chartCount === 0)<div style="width:100%;text-align:center;color:#94a3b8;padding-top:80px">لا توجد بيانات مالية كافية للرسم البياني.</div>@endif
            </div>
            <div class="hq-legend"><span><i class="hq-dot" style="background:#13b87a"></i> الإيرادات</span><span><i class="hq-dot" style="background:#ff4560"></i> المصروفات</span></div>
            <div class="hq-finance">
                <div class="hq-fin"><strong>{{ number_format($feePaid,2) }}</strong><span>مدفوع الشهر</span></div>
                <div class="hq-fin"><strong>{{ number_format($feeDue,2) }}</strong><span>متبقي الشهر</span></div>
                <div class="hq-fin"><strong>{{ number_format($balanceValue,2) }}</strong><span>الرصيد</span></div>
            </div>
        </div>
    </div>

    <div class="hq-notice">
        <i class="fas fa-shield-alt"></i>
        <div><strong>النظام يعمل ببيانات حقيقية.</strong> الإحصاءات أعلاه تُقرأ مباشرة من قاعدة بيانات المدرسة، وأي عملية من أزرار الإدارة تُرسل إلى المسار الأصلي للنظام مع صلاحيات المستخدم.</div>
    </div>
</div>
@stop

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.hq-action,.hq-stat,.hq-quick a,.hq-att-detail').forEach(function(el){
        el.addEventListener('click', function(){ el.style.opacity = '.82'; });
    });
});
</script>
@stop
