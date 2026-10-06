<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>إنشاء حساب — حقيبة المعلم الرقمية المتميزة</title>
<link href="css/bootstrap-cerulean.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body style="background:linear-gradient(135deg,#eff6ff,#f8fafc,#ecfeff);font-family:Cairo,sans-serif">
<div class="container" style="max-width:620px;margin:40px auto">
<div class="panel panel-default" style="border-radius:18px;box-shadow:0 15px 45px rgba(15,23,42,.10);border:0">
<div class="panel-body" style="padding:30px">
<h2 class="text-center" style="font-weight:800;color:#0f172a">إنشاء حساب جديد</h2>
<p class="text-center text-muted">حقيبة المعلم الرقمية المتميزة</p>
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="POST" action="{{ url('/register') }}">
@csrf
<div class="row"><div class="col-sm-6"><label>الاسم الأول</label><input name="firstname" class="form-control" required value="{{ old('firstname') }}"></div>
<div class="col-sm-6"><label>اسم العائلة</label><input name="lastname" class="form-control" required value="{{ old('lastname') }}"></div></div><br>
<label>اسم المستخدم</label><input name="login" class="form-control" required value="{{ old('login') }}"><br>
<label>البريد الإلكتروني</label><input type="email" name="email" class="form-control" required value="{{ old('email') }}"><br>
<label>كلمة المرور</label><input type="password" name="password" class="form-control" minlength="8" required><br>
<label>تأكيد كلمة المرور</label><input type="password" name="password_confirmation" class="form-control" minlength="8" required><br>
<button class="btn btn-primary btn-lg btn-block" type="submit">إنشاء الحساب</button>
<a class="btn btn-default btn-block" href="{{ url('/') }}">العودة لتسجيل الدخول</a>
<p class="text-center text-muted" style="margin-top:18px">الحسابات العامة لا تحصل على صلاحيات المالك.</p>
</form></div></div></div>
</body></html>