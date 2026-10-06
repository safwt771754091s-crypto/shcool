{{--<h1>ewe {{ Request::is('dashboard') ? 'active' : '' }}</h1>
--}}

<div class="logo">
  <a  class="js-arrow" href="#">
    
    @if(Session::get('inName')=='')
    <div style="font-family:Tajawal,Cairo,sans-serif;color:#fff;font-size:19px;font-weight:800;line-height:1.35;padding:8px 14px;">حقيبة المعلم<br>الرقمية المتميزة</div>
    @else
      <h2>{{Session::get('inName')}}</h2>
    @endif
    </a>
</div>
<div class="menu-sidebar2__content js-scrollbar1">
{{--<div class="account2">
  <div class="image img-cir img-120">
    <img src="{{ URL::asset('/assets/images/icon/avatar-big-01.jpg')}}" alt="John Doe" />
  </div>
  <h4 class="name">john doe</h4>
  <a  class="js-arrow" href="#">Sign out</a>
</div>--}}
<nav class="navbar-sidebar2">
  <ul class="list-unstyled navbar__list">

    <li class="{{ Request::is('dashboard') ? 'active' : '' }} has-sub"><a class="js-arrow" href="{{url('/dashboard')}}"> <i class="fas fa-tachometer-alt"></i><span> لوحة التحكم</span></a>
    </li>
    @if (Session::get('userRole') =="Director")
      <li class="has-sub">
        <a  class="js-arrow" href="#"><i class="glyphicon glyphicon-cog"></i><span> الإعدادات</span></a>
        <ul class="list-unstyled navbar__sub-list js-sub-list">
          <li><a href="{{url('/branches')}}">الفروع</a></li>
        </ul>
      </li>
    @endif
@if (Session::get('userRole') !="Director")
 {{-- @if (Session::get('userRole') =="Admin" )--}}
    @if(in_array('teacher_view',$permision) || in_array('teacher_add',$permision) || in_array('teacher_delete',$permision) || in_array('add_teacher_bulk_add',$permision))
      <li class="has-sub">
        <a  class="js-arrow {{ Request::is('teacher/*') ? 'open' : '' }}" href="#">
          <i class="glyphicon glyphicon-text-width"></i>
          المعلم
          <span class="arrow {{ Request::is('teacher/*') ? 'up' : '' }}">
            <i class="fas fa-angle-down"></i> 
          </span>
        </a>
        <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('teacher/*') ? 'block' : 'none' }} ;">
          @if(in_array('add_teacher_bulk_add',$permision))
            <li class="{{ Request::is('teacher/create-file') ? 'active' : '' }}"><a href="{{url('/teacher/create-file')}}">إضافة من ملف</a></li>
          @endif
          @if(in_array('teacher_add',$permision))
            <li class="{{ Request::is('teacher/create') ? 'active' : '' }}"><a href="{{url('/teacher/create')}}">إضافة جديد</a></li>
          @endif
          @if(in_array('teacher_view',$permision))
            <li class="{{ Request::is('teacher/list') ? 'active' : '' }}"><a href="{{url('/teacher/list')}}">قائمة المعلمين</a></li>
          @endif
          @if(in_array('teacher_timetable_add',$permision))
            <li class="{{ Request::is('teacher/create-timetable') ? 'active' : '' }}"><a href="{{url('/teacher/create-timetable')}}">إدارة الجدول الدراسي</a></li>
          @endif
        </ul>
      </li>
    @endif
     {{-- @if(in_array('class_add',$permision) || in_array('class_update',$permision) || in_array('class_delete',$permision) || in_array('class_view',$permision))
      <li class="has-sub {{ Request::is('class/list') ? '' : '' }}">
        <a  class="js-arrow {{ Request::is('class/*') ? 'open' : '' }} ;" href="#">
          <i class="glyphicon glyphicon-home"></i>
          الفصل
          <span class="arrow {{ Request::is('class/*') ? 'up' : '' }}">
            <i class="fas fa-angle-down"></i>
          </span>
        </a>
        <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('class/*') ? 'block' : 'none' }} ;">
          @if(in_array('class_add',$permision))
          <li class="has-sub {{ Request::is('class/create') ? 'active' : '' }}"><a href="{{url('/class/create')}}">إضافة New</a></li>
                    @endif
          @if( in_array('class_update',$permision) || in_array('class_delete',$permision) || in_array('class_view',$permision))
          <li class="has-sub {{ Request::is('class/list') ? 'active' : '' }}"><a href="{{url('/class/list')}}">الفصول</a></li>
          @endif
        </ul>
      </li>
      @endif--}}
      {{--@if(in_array('section_add',$permision) || in_array('section_update',$permision) || in_array('section_delete',$permision) || in_array('section_time_table',$permision) || in_array('section_view',$permision))
      <li class="has-sub">
        <a class="js-arrow {{ Request::is('section/*') ? 'open' : '' }}" href="#"><i class="fas fa-desktop"></i>
          الشعبة
          <span class="arrow {{ Request::is('section/*') ? 'up' : '' }}">
          <i class="fas fa-angle-down"></i>
          </span>
        </a>
        <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('section/*') ? 'block' : 'none' }} ;">
          @if(in_array('section_add',$permision))
            {<li class="{{ Request::is('section/create') ? 'active' : '' }}"><a href="{{url('/section/create')}}">إضافة New</a></li>
          
          @endif
          @if(in_array('section_view',$permision))
            <li class="{{ Request::is('section/list') ? 'active' : '' }}"><a href="{{url('/section/list')}}">الشُعب</a></li>
          @endif
        </ul>
      </li>
      @endif--}}
      @if(in_array('student_view',$permision) || in_array('student_add',$permision) || in_array('student_delete',$permision) || in_array('student_student_bulk_add',$permision))
      <li class="has-sub">
        <a  class="js-arrow {{ Request::is('student/*') ? 'open' : '' }}" href="#">
          <i class="glyphicon glyphicon-user"></i>
          بيانات الطلاب
          <span class="arrow {{ Request::is('student/*') ? 'up' : '' }}">
            <i class="fas fa-angle-down"></i> 
          </span>
        </a>
        <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('student/*') ? 'block' : 'none' }} ;">
          @if(in_array('student_student_bulk_add',$permision))
            <li class="{{ Request::is('student/create-file') ? 'active' : '' }}"><a href="{{url('/student/create-file')}}">إضافة from file</a></li>
          @endif
          @if(in_array('student_add',$permision))
            <li class="{{ Request::is('student/create') ? 'active' : '' }}"><a href="{{url('/student/create')}}">قبول طالب</a></li>
          @endif
          @if(in_array('student_view',$permision))
            <li class="{{ Request::is('student/list') ? 'active' : '' }}"><a href="{{url('/student/list')}}">بيانات الطلاب</a></li>
          @endif
          @if(family_check()=='on')
            <li class="{{ Request::is('family/list') ? 'active' : '' }}"><a href="{{url('/family/list')}}">بيانات أولياء الأمور</a></li>
          @endif
        {{--@if(in_array('promote_student',$permision) )
        <li class="{{ Request::is('promotion') ? 'active' : '' }} has-sub">
          <a href="{{url('/promotion')}}"><i class="glyphicon glyphicon-arrow-up"></i><span> الترقية</span></a>
        </li>
      @endif--}}
        </ul>
      </li>
    @endif
      @if(in_array('subject_view',$permision) || in_array('subject_add',$permision) || in_array('subject_update',$permision) || in_array('subject_delete',$permision) || in_array('section_add',$permision) || in_array('section_update',$permision) || in_array('section_delete',$permision) || in_array('section_time_table',$permision) || in_array('section_view',$permision) || in_array('class_add',$permision) || in_array('class_update',$permision) || in_array('class_delete',$permision) || in_array('class_view',$permision))
      <li class="has-sub">
       {{-- <a  class="js-arrow {{ Request::is('subject/*') ? 'open' : '' }}" href="#">
        --}}
        <a  class="js-arrow @if(Request::is('subject/*', 'section/*','promotion','class/*')) open @endif" href="#">
          <i class="glyphicon glyphicon-book"></i>
          <!-- المادة --> المواد الدراسية
          {{--<span class="arrow {{ Request::is('subject/*','class/*','promotion') ? 'up' : '' }}"><i class="fas fa-angle-down"></i> </span>
          --}}
          <span class="arrow  @if(Request::is('subject/*', 'section/*','class/*','promotion')) up @endif"><i class="fas fa-angle-down"></i> </span>
        </a>
        <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('subject/*','section/*','class/*','promotion') ? 'block' : 'none' }} ;">
          {{--@if(in_array('subject_add',$permision))
            <li class="{{ Request::is('subject/create') ? 'active' : '' }}"><a href="{{url('/subject/create')}}">إضافة New</a></li>
          @endif --}} 
          @if(in_array('subject_view',$permision)) 
            <li class="{{ Request::is('subject/list') ? 'active' : '' }}"><a href="{{url('/subject/list')}}">المواد</a></li>
          @endif
          @if(in_array('section_view',$permision))
            <li class="{{ Request::is('section/list') ? 'active' : '' }}"><a href="{{url('/section/list')}}">الشعبة القائمة</a></li>
          @endif
          @if( in_array('class_update',$permision) || in_array('class_delete',$permision) || in_array('class_view',$permision))
          <li class="has-sub {{ Request::is('class/list') ? 'active' : '' }}"><a href="{{url('/class/list')}}">الفصل القائمة</a></li>
          @endif

          @if(in_array('promote_student',$permision) )
        <li class="{{ Request::is('promotion') ? 'active' : '' }} has-sub">
          <a href="{{url('/promotion')}}"><span> الترفيع</span></a>
        </li>
      @endif
        </ul>
      </li>
      @endif
      
    {{--@if(in_array('student_view',$permision) || in_array('student_add',$permision) || in_array('student_delete',$permision) || in_array('student_student_bulk_add',$permision))
      <li class="has-sub">
        <a  class="js-arrow {{ Request::is('student/*') ? 'open' : '' }}" href="#">
          <i class="glyphicon glyphicon-user"></i>
          الطالب
          <span class="arrow {{ Request::is('student/*') ? 'up' : '' }}">
            <i class="fas fa-angle-down"></i> 
          </span>
        </a>
        <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('student/*') ? 'block' : 'none' }} ;">
          @if(in_array('student_student_bulk_add',$permision))
            <li class="{{ Request::is('student/create-file') ? 'active' : '' }}"><a href="{{url('/student/create-file')}}">إضافة from file</a></li>
          @endif
          @if(in_array('student_add',$permision))
            <li class="{{ Request::is('student/create') ? 'active' : '' }}"><a href="{{url('/student/create')}}">إضافة New</a></li>
          @endif
          @if(in_array('student_view',$permision))
            <li class="{{ Request::is('student/list') ? 'active' : '' }}"><a href="{{url('/student/list')}}">الطالب القائمة</a></li>
          @endif
          @if(family_check()=='on')
<!--             <li class="{{ Request::is('family/list') ? 'active' : '' }}"><a href="{{url('/family/list')}}">Family القائمة</a></li>
 -->          @endif
        </ul>
      </li>
    @endif--}}

 {{-- @endif --}}

    {{--@if(in_array('teacher_view',$permision) || in_array('teacher_add',$permision) || in_array('teacher_delete',$permision) || in_array('add_teacher_bulk_add',$permision))
      <li class="has-sub">
        <a  class="js-arrow {{ Request::is('teacher/*') ? 'open' : '' }}" href="#">
          <i class="glyphicon glyphicon-text-width"></i>
          المعلم
          <span class="arrow {{ Request::is('teacher/*') ? 'up' : '' }}">
            <i class="fas fa-angle-down"></i> 
          </span>
        </a>
        <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('teacher/*') ? 'block' : 'none' }} ;">
          @if(in_array('add_teacher_bulk_add',$permision))
            <li class="{{ Request::is('teacher/create-file') ? 'active' : '' }}"><a href="{{url('/teacher/create-file')}}">إضافة from file</a></li>
          @endif
          @if(in_array('teacher_add',$permision))
            <li class="{{ Request::is('teacher/create') ? 'active' : '' }}"><a href="{{url('/teacher/create')}}">إضافة New</a></li>
          @endif
          @if(in_array('teacher_view',$permision))
            <li class="{{ Request::is('teacher/list') ? 'active' : '' }}"><a href="{{url('/teacher/list')}}">المعلم القائمة</a></li>
          @endif
          @if(in_array('teacher_timetable_add',$permision))
            <li class="{{ Request::is('teacher/create-timetable') ? 'active' : '' }}"><a href="{{url('/teacher/create-timetable')}}">Timetable Management</a></li>
          @endif
        </ul>
      </li>
    @endif--}}
    @if(in_array('add_student_attendance',$permision) || in_array('view_student_attendance',$permision) || in_array('view_student_monthly_reports',$permision))
      <li class="has-sub">
        <a  class="js-arrow {{ Request::is('attendance/*') ? 'open' : '' }}" href="#">
          <i class="glyphicon glyphicon-pencil"></i>
          الحضور والغياب
          <span class="arrow {{ Request::is('attendance/*') ? 'up' : '' }}">
            <i class="fas fa-angle-down"></i> 
          </span>
        </a>
        <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('attendance/*') ? 'block' : 'none' }} ;">

          <!-- <li><a href="/attendance/create-file">إضافة from file</a></li>-->
          @if(in_array('add_student_attendance',$permision))
            <li class="{{ Request::is('attendance/create') ? 'active' : '' }}"><a href="{{url('/attendance/create')}}">إضافة</a></li>
          @endif 
          @if(in_array('view_student_attendance',$permision))
            <li class="{{ Request::is('attendance/list') ? 'active' : '' }}"><a href="{{url('/attendance/list')}}">عرض</a></li>
          @endif
          @if(in_array('view_student_monthly_reports',$permision))
           <li class="{{ Request::is('attendance/monthly-report') ? 'active' : '' }}"><a href="{{url('/attendance/monthly-report')}}"><i class="glyphicon glyphicon-print"></i> Monthly الحضور والغياب التقرير</a></li>
          @endif
        </ul>
      </li>
    @endif

    @if(in_array('exam_view',$permision) || in_array('exam_add',$permision)|| in_array('paper_add',$permision) || in_array('paper_view',$permision) || in_array('paper_update',$permision) || in_array('paper_delete',$permision)|| in_array('add_marks',$permision) || in_array('view_marks',$permision) || in_array('generate_result',$permision) || in_array('search_result',$permision))
      <li class="has-sub">
        <a  class="js-arrow {{ Request::is('exam/*','question/*','paper/*','mark/*','result/*') ? 'open' : '' }}" href="#">
          <i class="glyphicon glyphicon-fire"></i>
          الاختبارinations
          <span class="arrow {{ Request::is('exam/*','question/*','paper/*','mark/*','result/*') ? 'up' : '' }}">
            <i class="fas fa-angle-down"></i> 
          </span>
        </a>
        <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('exam/*','question/*','paper/*','mark/*','result/*') ? 'block' : 'none' }} ;">
          @if(in_array('exam_add',$permision))
            {{--<li class="{{ Request::is('exam/create') ? 'active' : '' }}"><a href="{{url('/exam/create')}}">إضافة New</a></li>
          --}}
          @endif
          @if(in_array('exam_view',$permision))
            <li class="{{ Request::is('exam/list') ? 'active' : '' }}"><a href="{{url('/exam/list')}}">الاختبار القائمة</a></li>
          @endif
          @if(in_array('paper_add',$permision))
          <li class="{{ Request::is('question/create') ? 'active' : '' }}"><a href="{{url('/question/create')}}">إضافة New الأسئلة</a></li>
          @endif
          @if(in_array('paper_view',$permision))
          <li class="{{ Request::is('question/list') ? 'active' : '' }}"><a href="{{url('/question/list')}}">السؤال القائمة</a></li>
          @endif
          @if(in_array('paper_add',$permision))
          <li class="{{ Request::is('paper/generate') ? 'active' : '' }}"><a href="{{url('/paper/generate')}}"> Generate Paper</a></li>
          @endif

          @if($system_grade=='' || $system_grade=='auto')
            @if(in_array('add_marks',$permision))
              <li class="{{ Request::is('mark/create') ? 'active' : '' }}"><a href="{{url('/mark/create')}}">إضافة New</a></li>
            @endif
            @if(in_array('view_marks',$permision))
              <li class="{{ Request::is('mark/list') ? 'active' : '' }}"><a href="{{url('/mark/list')}}">الدرجات القائمة</a></li>
            @endif
          @else
            @if(in_array('add_marks',$permision))
              <li class="{{ Request::is('mark/m_create') ? 'active' : '' }}"><a href="{{url('/mark/m_create')}}">إضافة الدرجات</a></li>
            @endif
            @if(in_array('view_marks',$permision))
              <li class="{{ Request::is('mark/m_list') ? 'active' : '' }}"><a href="{{url('/mark/m_list')}}">الدرجات القائمة</a></li>
            @endif
          @endif
          @if(in_array('generate_result',$permision))
              <li class="{{ Request::is('result/generate') ? 'active' : '' }}"><a href="{{url('/result/generate')}}">Generate Result</a></li>
            @endif
            @if(in_array('search_result',$permision))
              {{--<li class="{{ Request::is('result/search') ? 'active' : '' }}"><a href="{{url('/result/search')}}">بحث</a></li>
              <li class="{{ Request::is('results') ? 'active' : '' }}"><a href="{{url('/results')}}">بحث Public</a></li>--}}
            @endif
          <li><a href="{{url('/template/creates')}}">القوالب</a></li>
        </ul>
      </li>
    @endif
  {{--@if(in_array('paper_add',$permision) || in_array('paper_view',$permision) || in_array('paper_update',$permision) || in_array('paper_delete',$permision))

    <li class="has-sub">
          <a  class="js-arrow {{ Request::is('question/*') ? 'open' : '' }} {{ Request::is('paper/generate') ? 'open' : '' }} " href="#">
          <i class="glyphicon glyphicon-hdd"></i>
          Paper Management
          <span class="arrow {{ Request::is('question/*') ? 'up' : '' }}  {{ Request::is('paper/generate') ? 'up' : '' }} ">
            <i class="fas fa-angle-down"></i>
          </span>
        
        </a>
        <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('question/*') ? 'block' : '' }}  {{ Request::is('paper/generate') ? 'block' : '' }};" href="#">

          @if(in_array('paper_add',$permision))
          <li class="{{ Request::is('question/create') ? 'active' : '' }}"><a href="{{url('/question/create')}}">إضافة New</a></li>
          @endif
          @if(in_array('paper_view',$permision))
          <li class="{{ Request::is('question/list') ? 'active' : '' }}"><a href="{{url('/question/list')}}">القائمة</a></li>
          @endif
          @if(in_array('paper_add',$permision))
          <li class="{{ Request::is('paper/generate') ? 'active' : '' }}"><a href="{{url('/paper/generate')}}"> Generate Paper</a></li>
          @endif
        </ul>
      </li>
    @endif--}}
    
    {{--@if(in_array('add_marks',$permision) || in_array('view_marks',$permision))
      <li class="has-sub">
          <a  class="js-arrow {{ Request::is('mark/*') ? 'open' : '' }}" href="#">
          <i class="glyphicon glyphicon-list-alt"></i>
          الدرجة Manage
          <span class="arrow {{ Request::is('mark/*') ? 'up' : '' }}">
            <i class="fas fa-angle-down"></i> 
          </span>
        </a>
        <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('mark/*') ? 'block' : 'none' }} ;">
          @if($system_grade=='' || $system_grade=='auto')
            @if(in_array('add_marks',$permision))
              <li class="{{ Request::is('mark/create') ? 'active' : '' }}"><a href="{{url('/mark/create')}}">إضافة New</a></li>
            @endif
            @if(in_array('view_marks',$permision))
              <li class="{{ Request::is('mark/list') ? 'active' : '' }}"><a href="{{url('/mark/list')}}">الدرجات القائمة</a></li>
            @endif
          @else
            @if(in_array('add_marks',$permision))
              <li class="{{ Request::is('mark/m_create') ? 'active' : '' }}"><a href="{{url('/mark/m_create')}}">إضافة New</a></li>
            @endif
            @if(in_array('view_marks',$permision))
              <li class="{{ Request::is('mark/m_list') ? 'active' : '' }}"><a href="{{url('/mark/m_list')}}">الدرجات القائمة</a></li>
            @endif
          @endif
          <li><a href="{{url('/template/creates')}}">القوالب</a></li>
        </ul>
      </li>
    @endif--}}
    {{--@if (Session::get('userRole') =="Admin")--}}
      {{--@if(in_array('generate_result',$permision) || in_array('search_result',$permision))
        <li class="has-sub">
          <a  class="js-arrow {{ Request::is('result/*') ? 'open' : '' }}" href="#">
            <i class="glyphicon  glyphicon glyphicon-list"></i>
            Result
            <span class="arrow {{ Request::is('result/*') ? 'up' : '' }}">
              <i class="fas fa-angle-down"></i> 
            </span>
          </a>
          <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('result/*') ? 'block' : 'none' }} ;">
            @if(in_array('generate_result',$permision))
              <li class="{{ Request::is('result/generate') ? 'active' : '' }}"><a href="{{url('/result/generate')}}">Generate</a></li>
            @endif
            @if(in_array('search_result',$permision))
              <li class="{{ Request::is('result/search') ? 'active' : '' }}"><a href="{{url('/result/search')}}">بحث</a></li>
              <li class="{{ Request::is('results') ? 'active' : '' }}"><a href="{{url('/results')}}">بحث Public</a></li>
            @endif
          </ul>
        </li>
      @endif--}}
        {{--@if(in_array('accunting',$permision))--}}
        @if( accounting_check()=='yes')
       <li class="has-sub">
          <a  class="js-arrow {{ Request::is('accounting/*') ? 'open' : '' }}" href="#">
            <i class="glyphicon  glyphicon glyphicon-font"></i>
            المحاسبة
            <span class="arrow {{ Request::is('accounting/*') ? 'up' : '' }}">
              <i class="fas fa-angle-down"></i> 
            </span>
          </a>
          <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('accounting/*') ? 'block' : 'none' }} ;">
            <li class="{{ Request::is('accounting/sectors') ? 'active' : '' }}"><a href="{{url('/accounting/sectors')}}">Sectors</a></li>
            <li class="{{ Request::is('accounting/income') ? 'active' : '' }}"><a href="{{url('/accounting/income')}}">إضافة Income</a></li>
            <li class="{{ Request::is('accounting/incomelist') ? 'active' : '' }}"><a href="{{url('/accounting/incomelist')}}">عرض Income</a></li>
            <li class="{{ Request::is('accounting/expence') ? 'active' : '' }}"><a href="{{url('/accounting/expence')}}">إضافة Expence</a></li>
            <li class="{{ Request::is('accounting/expencelist') ? 'active' : '' }}"><a href="{{url('/accounting/expencelist')}}">عرض Expence</a></li>
          </ul>
        </li>
        @endif
      
      @if(in_array('send_notification',$permision) )
       

           <li class="has-sub">
            <a  class="js-arrow {{ Request::is('message','notification_type','ictcore/attendance') ? 'open' : '' }}" href="#">
            <i class="glyphicon glyphicon-envelope"></i>
            Communication
            <span class="arrow {{ Request::is('message','notification_type','ictcore/attendance') ? 'up' : '' }}">
              <i class="fas fa-angle-down"></i> 
            </span>
          </a>
          <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('message','notification_type','ictcore/attendance') ? 'block' : 'none' }} ;">
           <li class="{{ Request::is('message') ? 'active' : '' }}" > <a href="{{url('/message')}}"> Communication</a></li>
           <li class="{{ Request::is('notification_type') ? 'active' : '' }}"><a href="{{url('/notification_type')}}">الإشعارات Types</a></li>
           <li class="{{ Request::is('ictcore/attendance') ? 'active' : '' }}"><a href="{{url('/ictcore/attendance')}}">الإشعاراتs</a></li>
          </ul>
        </li>







      @endif
    {{--@endif--}}
        @if (Session::get('userRole') =="Admin")
        @endif
      @if (Session::get('userRole')=="Admin")

            <li class="has-sub">
          <a  class="js-arrow {{ Request::is('template/*') ? 'open' : '' }}" href="#">
            <i class="glyphicon  glyphicon glyphicon-font"></i>
            القوالب
            <span class="arrow {{ Request::is('template/*') ? 'up' : '' }}">
              <i class="fas fa-angle-down"></i> 
            </span>
          </a>
          <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('template/*') ? 'block' : 'none' }} ;">
            <li class="{{ Request::is('template/create') ? 'active' : '' }}"><a href="{{url('/template/create')}}">إضافة القوالب</a></li>
            <li class="{{ Request::is('template/list') ? 'active' : '' }}"><a href="{{url('/template/list')}}">القوالبs</a></li>
            
          </ul>
        </li>
      
        <li class="has-sub">
          <a  class="js-arrow {{ Request::is('academicYear', 'gpa', 'users', 'holidays', 'class-off', 'institute', 'ictcore?type=sms', 'ictcore?type=voice','permission','accounting') ? 'open' : '' }}" href="#">
            <i class="glyphicon glyphicon-cog"></i>
             الإعدادات 
            <span class="arrow {{ Request::is('academicYear', 'gpa', 'users', 'holidays', 'class-off', 'institute', 'ictcore?type=sms', 'ictcore?type=voice','permission','accounting') ? 'up' : '' }}">
              <i class="fas fa-angle-down"></i> 
            </span>                            
          </a>
          <ul class="list-unstyled navbar__sub-list js-sub-list" style="display:{{ Request::is('academicYear', 'gpa', 'users', 'holidays', 'class-off', 'institute', 'ictcore?type=sms', 'ictcore?type=voice','permission','accounting') ? 'block' : 'none' }} ;">
            <li class="{{ Request::is('academicYear') ? 'active' : '' }}"><a href="{{url('/academicYear')}}">العام الدراسي</a></li>
            <li class="{{ Request::is('gpa') ? 'active' : '' }}"><a href="{{url('/gpa')}}">قواعد المعدل</a></li>
            <li class="{{ Request::is('users') ? 'active' : '' }}"><a href="{{url('/users')}}">المستخدمون</a></li>
            <li class="{{ Request::is('holidays') ? 'active' : '' }}"><a href="{{url('/holidays')}}">العطل</a></li>
            <li class="{{ Request::is('class-off') ? 'active' : '' }}"><a href="{{url('/class-off')}}">الفصل Off Days</a></li>
            <li class="{{ Request::is('institute') ? 'active' : '' }}"><a href="{{url('/institute')}}">بيانات المدرسة</a></li>
             @if(Auth::user()->login=='ictkashif')
            <li class="{{ Request::is('ictcore?type=sms') ? 'active' : '' }}"><a href="{{url('/ictcore?type=sms')}}">Sms Integration</a></li>
            <li class="{{ Request::is('ictcore?type=voice') ? 'active' : '' }}"><a href="{{url('/ictcore?type=voice')}}">المكالمات الصوتية Integration</a></li>
           @endif
            
            <li class="{{ Request::is('permission') ? 'active' : '' }}"><a href="{{url('/permission')}}">الصلاحيات</a></li>
            {{--@if(accounting_check()!='' && accounting_check()=='yes' )
             <li class="{{ Request::is('accounting') ? 'active' : '' }}"><a href="{{url('/accounting')}}">المحاسبة Api</a></li>
             @endif --}}
          </ul>
        </li>
        @endif
        </li>
      @endif
  
