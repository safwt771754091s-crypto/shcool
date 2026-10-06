@extends('layouts.master')

@section('style')
<style>
.users-page{direction:rtl}.users-hero{background:linear-gradient(135deg,#1d4ed8,#06b6d4);color:#fff;border-radius:22px;padding:24px 26px;margin-bottom:20px;box-shadow:0 14px 35px rgba(15,23,42,.12)}.users-hero h2{margin:0 0 6px;font-weight:800}.users-card{background:#fff;border:1px solid #e5e7eb;border-radius:20px;box-shadow:0 10px 28px rgba(15,23,42,.06);margin-bottom:20px;overflow:hidden}.users-card .card-head{padding:18px 22px;border-bottom:1px solid #eef2f7;display:flex;align-items:center;justify-content:space-between;gap:12px}.users-card .card-body{padding:22px}.field{margin-bottom:16px}.field label{font-weight:700;color:#334155;display:block;margin-bottom:7px}.avatar-preview{width:74px;height:74px;border-radius:50%;object-fit:cover;border:3px solid #e2e8f0;background:#f8fafc}.user-avatar{width:44px;height:44px;border-radius:50%;object-fit:cover;background:#e2e8f0}.initial-avatar{width:44px;height:44px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#dbeafe;color:#1d4ed8;font-weight:800}.role{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;background:#eef2ff;color:#3730a3}.actions{white-space:nowrap}.users-table td,.users-table th{vertical-align:middle!important}.btn-main{background:#2563eb;color:#fff;border:0;border-radius:10px;padding:10px 16px;font-weight:700}.btn-main:hover{color:#fff;background:#1d4ed8}.muted{color:#64748b;font-size:12px}
</style>
@stop

@section('content')
<div class="users-page">
    @if(session('success'))<div class="alert alert-success"><strong>{{ session('success') }}</strong></div>@endif
    @if(session('error'))<div class="alert alert-danger"><strong>{{ session('error') }}</strong></div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><strong>يرجى مراجعة البيانات:</strong><ul style="margin:8px 0 0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="users-hero">
        <h2><i class="glyphicon glyphicon-user"></i> إدارة المستخدمين</h2>
        <div>إدارة الحسابات والصلاحيات وكلمات المرور والصور الشخصية من مكان واحد.</div>
    </div>

    <div class="users-card">
        <div class="card-head">
            <strong>{{ $user ? 'تعديل حساب المستخدم' : 'إضافة مستخدم جديد' }}</strong>
            @if($user)<a class="btn btn-default" href="{{ url('/users') }}">مستخدم جديد</a>@endif
        </div>
        <div class="card-body">
            <form role="form" action="{{ $user ? url('/userupdate') : url('/usercreate') }}" method="post" enctype="multipart/form-data">
                @csrf
                @if($user)<input type="hidden" name="id" value="{{ $user->id }}">@endif
                <div class="row">
                    <div class="col-md-3 field"><label>الاسم الأول</label><input class="form-control input-lg" required name="firstname" value="{{ old('firstname',$user->firstname ?? '') }}"></div>
                    <div class="col-md-3 field"><label>اسم العائلة</label><input class="form-control input-lg" required name="lastname" value="{{ old('lastname',$user->lastname ?? '') }}"></div>
                    <div class="col-md-3 field"><label>البريد الإلكتروني</label><input class="form-control input-lg" type="email" required name="email" value="{{ old('email',$user->email ?? '') }}"></div>
                    <div class="col-md-3 field"><label>اسم المستخدم</label><input class="form-control input-lg" required name="login" value="{{ old('login',$user->login ?? '') }}" {{ $user && strtolower($user->email)===strtolower(env('OWNER_EMAIL','Safwt771754091s@gmail.com')) ? 'readonly' : '' }}></div>
                </div>
                <div class="row">
                    <div class="col-md-3 field">
                        <label>الصلاحية</label>
                        <select name="group" class="form-control input-lg" required {{ $user && strtolower($user->email)===strtolower(env('OWNER_EMAIL','Safwt771754091s@gmail.com')) ? 'disabled' : '' }}>
                            @foreach(['Director'=>'مدير','Admin'=>'مدير نظام','Teacher'=>'معلم','Accountant'=>'محاسب','Staff'=>'موظف','Other'=>'مستخدم'] as $value=>$label)
                                <option value="{{ $value }}" {{ old('group',$user->group ?? 'Staff')===$value?'selected':'' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if($user && strtolower($user->email)===strtolower(env('OWNER_EMAIL','Safwt771754091s@gmail.com')))<input type="hidden" name="group" value="Admin">@endif
                    </div>
                    <div class="col-md-3 field"><label>كلمة المرور {{ $user ? '(اختياري)' : '' }}</label><input class="form-control input-lg" type="password" name="password" {{ $user ? '' : 'required' }} autocomplete="new-password"></div>
                    <div class="col-md-3 field"><label>تأكيد كلمة المرور</label><input class="form-control input-lg" type="password" name="password_confirmation" {{ $user ? '' : 'required' }} autocomplete="new-password"></div>
                    <div class="col-md-3 field"><label>الصورة الشخصية</label><input class="form-control" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" onchange="previewUserAvatar(this)"><small class="muted">JPG / PNG / WebP — حتى 2MB</small></div>
                </div>
                <div class="row">
                    <div class="col-md-9 field"><label>الوصف</label><textarea class="form-control" name="desc" rows="3" maxlength="1000" placeholder="وصف مختصر للحساب">{{ old('desc',$user->desc ?? '') }}</textarea></div>
                    <div class="col-md-3 text-center">
                        @if($user && !empty($user->avatar))
                            <img id="avatarPreview" class="avatar-preview" src="{{ asset('storage/'.$user->avatar) }}" alt="صورة الحساب">
                        @else
                            <div id="avatarFallback" class="initial-avatar" style="width:74px;height:74px;font-size:28px">{{ mb_substr($user->firstname ?? 'م',0,1) }}</div>
                            <img id="avatarPreview" class="avatar-preview" style="display:none" alt="معاينة الصورة">
                        @endif
                    </div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button class="btn-main" type="submit"><i class="glyphicon glyphicon-floppy-disk"></i> {{ $user ? 'حفظ التعديلات' : 'إنشاء الحساب' }}</button>
                    @if($user)<a class="btn btn-default" href="{{ url('/users') }}">إلغاء</a>@endif
                </div>
            </form>
        </div>
    </div>

    <div class="users-card">
        <div class="card-head"><strong>الحسابات المسجلة</strong><span class="muted">{{ count($users) }} حساب</span></div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="sectorList" class="table table-striped table-bordered table-hover users-table">
                    <thead><tr><th>المستخدم</th><th>البريد</th><th>اسم الدخول</th><th>الصلاحية</th><th>الوصف</th><th>الإجراءات</th></tr></thead>
                    <tbody>
                    @foreach($users as $row)
                        <tr>
                            <td>
                                @if(!empty($row->avatar))<img class="user-avatar" src="{{ asset('storage/'.$row->avatar) }}" alt="صورة">@else<span class="initial-avatar">{{ mb_substr($row->firstname ?: 'م',0,1) }}</span>@endif
                                <strong style="margin-right:8px">{{ $row->firstname }} {{ $row->lastname }}</strong>
                            </td>
                            <td>{{ $row->email }}</td><td>{{ $row->login }}</td>
                            <td><span class="role">{{ $row->group === 'Admin' ? 'مدير نظام' : ($row->group === 'Teacher' ? 'معلم' : ($row->group === 'Accountant' ? 'محاسب' : $row->group)) }}</span></td>
                            <td>{{ $row->desc }}</td>
                            <td class="actions">
                                <a title="تعديل" class="btn btn-info" href="{{ url('/useredit/'.$row->id) }}"><i class="glyphicon glyphicon-edit"></i></a>
                                @if($row->id !== Auth::id())
                                    @if(strtolower($row->email)!==strtolower(env('OWNER_EMAIL','Safwt771754091s@gmail.com')))
                                        <a title="حذف" class="btn btn-danger" href="{{ url('/userdelete/'.$row->id) }}" onclick="return confirm('هل أنت متأكد من حذف هذا الحساب؟')"><i class="glyphicon glyphicon-trash"></i></a>
                                    @endif
                                    <a title="دخول كالمستخدم" class="btn btn-warning" href="{{ url('/login/'.$row->id) }}" onclick="return confirm('سيتم الدخول بهذا الحساب ويمكنك العودة إلى حساب المدير من تسجيل الخروج. متابعة؟')"><i class="glyphicon glyphicon-log-in"></i></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop

@section('script')
<script>
function previewUserAvatar(input){
    const img=document.getElementById('avatarPreview'), fallback=document.getElementById('avatarFallback');
    if(input.files && input.files[0]){
        img.src=URL.createObjectURL(input.files[0]); img.style.display='inline-block'; if(fallback) fallback.style.display='none';
    }
}
$(document).ready(function(){ $('#sectorList').dataTable({"sPaginationType":"bootstrap"}); });
</script>
@stop
