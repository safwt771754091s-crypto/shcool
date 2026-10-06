@extends('layouts.master')

@section('style')
<style>
.profile-shell{max-width:1000px;margin:0 auto}.profile-card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;box-shadow:0 12px 35px rgba(15,23,42,.07);overflow:hidden}.profile-hero{padding:28px;background:linear-gradient(135deg,#2563eb,#06b6d4);color:#fff}.profile-body{padding:28px}.avatar-wrap{position:relative;width:128px;height:128px}.avatar{width:128px;height:128px;border-radius:50%;object-fit:cover;border:5px solid rgba(255,255,255,.85);background:#e2e8f0}.avatar-fallback{width:128px;height:128px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff;color:#2563eb;font-size:46px;font-weight:800;border:5px solid rgba(255,255,255,.85)}.field{margin-bottom:18px}.field label{font-weight:700;color:#334155}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn-save{background:#2563eb;color:#fff;border:0;border-radius:10px;padding:11px 22px;font-weight:700}.btn-save:hover{background:#1d4ed8;color:#fff}.muted{color:#64748b}.role-badge{display:inline-block;padding:5px 10px;border-radius:999px;background:rgba(255,255,255,.18);font-size:13px;margin-top:8px}@media(max-width:767px){.profile-body{padding:18px}.profile-hero{text-align:center}.avatar-wrap{margin:auto}}
</style>
@stop

@section('content')
<div class="profile-shell" dir="rtl">
    @if(session('success'))
        <div class="alert alert-success"><strong>{{ session('success') }}</strong></div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>يرجى مراجعة البيانات:</strong>
            <ul style="margin:8px 0 0;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="profile-card">
        <div class="profile-hero">
            <div class="avatar-wrap">
                @if(!empty($user->avatar))
                    <img class="avatar" src="{{ asset('storage/'.$user->avatar) }}" alt="صورة الحساب">
                @else
                    <div class="avatar-fallback">{{ mb_substr($user->firstname ?: 'م',0,1) }}</div>
                @endif
            </div>
            <h2 style="margin:16px 0 4px;font-weight:800;">الملف الشخصي</h2>
            <div>{{ $user->firstname }} {{ $user->lastname }}</div>
            <span class="role-badge">{{ $user->group === 'Admin' ? 'مدير / مالك المنصة' : 'حساب مستخدم' }}</span>
        </div>

        <div class="profile-body">
            <form method="POST" action="{{ url('/profile') }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-6 field">
                        <label>الاسم الأول</label>
                        <input class="form-control input-lg" name="firstname" value="{{ old('firstname',$user->firstname) }}" required>
                    </div>
                    <div class="col-md-6 field">
                        <label>اسم العائلة</label>
                        <input class="form-control input-lg" name="lastname" value="{{ old('lastname',$user->lastname) }}" required>
                    </div>
                    <div class="col-md-6 field">
                        <label>البريد الإلكتروني</label>
                        <input class="form-control input-lg" type="email" name="email" value="{{ old('email',$user->email) }}" required>
                    </div>
                    <div class="col-md-6 field">
                        <label>اسم المستخدم</label>
                        <input class="form-control input-lg" value="{{ $user->login }}" readonly>
                    </div>
                    <div class="col-md-6 field">
                        <label>صورة الحساب</label>
                        <input class="form-control" type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
                        <small class="muted">JPG / PNG / WebP — حتى 2MB</small>
                    </div>
                    <div class="col-md-6 field">
                        <label>الصلاحية</label>
                        <input class="form-control input-lg" value="{{ $user->group }}" readonly>
                    </div>
                    <div class="col-md-6 field">
                        <label>كلمة مرور جديدة <span class="muted">(اختياري)</span></label>
                        <input class="form-control input-lg" type="password" name="password" autocomplete="new-password">
                    </div>
                    <div class="col-md-6 field">
                        <label>تأكيد كلمة المرور</label>
                        <input class="form-control input-lg" type="password" name="password_confirmation" autocomplete="new-password">
                    </div>
                </div>
                <div class="actions">
                    <button class="btn-save" type="submit"><i class="glyphicon glyphicon-floppy-disk"></i> حفظ التغييرات</button>
                    <a class="btn btn-default" href="{{ url('/dashboard') }}">العودة للوحة التحكم</a>
                    <a class="btn btn-danger" href="{{ url('/users/logout') }}"><i class="glyphicon glyphicon-log-out"></i> تسجيل الخروج</a>
                </div>
            </form>
        </div>
    </div>
</div>
@stop
