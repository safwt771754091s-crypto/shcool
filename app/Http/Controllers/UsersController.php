<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use App\Http\Controllers\ICTCoreController;
use App\Models\Institute;
use App\Models\User;
use App\Models\VerifyCode;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

use Hash;

class UsersController extends BaseController
{

  // use AuthenticatesUsers;
  public function __construct()
  {
    /*$this->beforeFilter('csrf', array('on'=>'post'));
    $this->beforeFilter('auth', array('only'=>array('show','create','edit','update')));
    $this->beforeFilter('userAccess',array('only'=> array('show','create','edit','update','delete')));*/
    //5.5
    //$this->middleware('csrf', array('on'=>'post'));
    $this->middleware('auth', array('only' => array('show', 'create', 'edit', 'update', 'dologin')));
  }
  /**
   * Display a listing of the resource.
   *
   * @return Response
   */
  public function showRegistration()
  {
    $institute = Institute::select('name')->first();
    return view('register', compact('institute'));
  }

  public function register(Request $request)
  {
    $data = $request->validate([
      'firstname' => 'required|string|max:100',
      'lastname' => 'required|string|max:100',
      'login' => 'required|string|max:100|unique:users,login',
      'email' => 'required|email|max:190|unique:users,email',
      'password' => 'required|string|min:8|confirmed',
    ]);

    User::create([
      'firstname' => $data['firstname'],
      'lastname' => $data['lastname'],
      'login' => $data['login'],
      'email' => $data['email'],
      'group' => 'Other',
      'desc' => 'Public registration',
      'password' => Hash::make($data['password']),
    ]);

    return Redirect::to('/')->with('message', 'تم إنشاء الحساب بنجاح. يمكنك تسجيل الدخول الآن.');
  }

  public function postSignin(request $request)
  {

    $otp_check  = \Config::get('app.otp');

    //echo "fcf".$otp_check ;
    $institute = Institute::select('name')->first();

    if ($otp_check == "No") {
      if (\Auth::attempt(array('login' => $request->input('login'), 'password' => $request->input('password')))) {

        $login   = Auth::user()->group;
        /*if($login == "Admin"){
              $user_id = Auth::user()->id;
              $phone = Auth::user()->phone;
            \Auth::logout();
            $this->sendcode($user_id,$phone);
            return Redirect::to('/verify_code');

          }*/


        $name = Auth::user()->firstname . ' ' . Auth::user()->lastname;
        $login = Auth::user()->group;
        Session::put('name', $name);
        Session::put('userRole', $login);

        if (!$institute) {
          if (Auth::user()->group != "Admin") {
            return Redirect::to('/')
              ->withInput($request->all())->with('error', 'Institute Information not setup yet!Please contact administrator.');
          } else {
            $institute = new Institute;
            $institute->name = "IctVission";
            \Session::put('inName', $institute->name);
            return Redirect::to('/institute')->with('error', 'Please provide institute information!');
          }
        } else {
          \Session::put('inName', $institute->name);
          return Redirect::to('/dashboard')->with('success', 'You are now logged in.');
        }
      } else {
        return Redirect::to('/')
          ->withInput($request->all())->with('error', 'Your username/password combination was incorrect');
      }
    } else {

      // $this->validateLogin($request);

      if (\Auth::attempt(array('login' => $request->input('login'), 'password' => $request->input('password')))) {
        //if ($user = app('auth')->getProvider()->retrieveByCredentials($request->only('email', 'password'))) {
        $user_id = Auth::id();
        $token = VerificationCode::where('user_id', $user_id)->where('ip_address', $request->ip());
        if ($token->count() == 0) {


          $token_create = VerificationCode::create(
            [

              'user_id' => $user_id,
              'status' => 1,
              'ip_address' => $request->ip()
            ]
          );         //$code = $token->first()->generateCode;exit;

          $ict     = new ICTCoreController();

          if (preg_match("~^0\d+$~", Auth()->user()->phone)) {
            $phone = preg_replace('/0/', '92', Auth()->user()->phone, 1);
          } else {
            $phone = Auth()->user()->phone;
          }
          $message = 'Your verification code is ' . $token_create->code;
          $data = array('numbers' => $phone, 'message' => $message);
          $snd_msg  = $ict->biz_sms($data);
          //exit;
          \Auth::logout();
          return Redirect::to('/verification_code?id=' . $token_create->id);
        }



        \Session::put('inName', $institute->name);
        return Redirect::to('/dashboard')->with('success', 'You are now logged in.');
      } else {

        return Redirect::to('/')
          ->withInput($request->all())->with('error', 'Your username/password combination was incorrect');
      }
    }
  }

  public function codeverify(Request $request)
  {
    $institute = Institute::select('name')->first();
    if (!$institute) {
      $institute = new Institute;
      $institute->name = "ictvission";
    }
    $id = $request->get('id');
    return view('verificationcode', compact('institute', 'id'));
  }
  public function code_check(Request $request)
  {

    $check = VerificationCode::find($request->id);

    if ($check->code == $request->code) {

      if (Auth::loginUsingId($check->user_id)) {
        //request()->session()->flush();
        $name  = Auth::user()->firstname . ' ' . Auth::user()->lastname;
        $login = Auth::user()->group;
        \Session::put('name', $name);
        \Session::put('userRole', $login);
        return redirect('/dashboard');
      }
    }

    return Redirect::to('/verification_code?id=' . $request->id)
      ->withInput($request->all())->withErrors('Your code was incorrect');
  }

  public function verify_code(Request $request)
  {
    $error = \Session::get('error');
    $institute = Institute::select('name')->first();
    if (!$institute) {
      $institute = new Institute;
      $institute->name = "IctVission";
    }
    return View('app.users.verify', compact('error', 'institute'));
  }

  public function sendcode($user_id, $phone)
  {

    $verified_code = hexdec(substr(uniqid(rand(), true), 5, 5));
    $verification_code = new VerifyCode;
    $verification_code->user_id = $user_id;
    $verification_code->code = $verified_code;
    $verification_code->save();

    /* $ict         = new ICTCoreController();
                    $contact = array(
                      'firstname' => 'admin',
                      'lastname' =>'',
                      'phone'     =>$phone,
                      'email'     => '',
                      );
                $msg = "verification code is ". $verified_code;
                 $ict_stting = DB::table('ict_settings')->first();
                 if($ict_stting->type=='ictcore'){
                $ict->verification_number($contact,$msg);*/
    $msg = "verification code is " . $verified_code;
    $send_msg_ictcore = sendmesssageictcore('admin', '', $phone, $msg, 'verified code');
  }

  public function verified(Request $request)
  {
    $verification_code = VerifyCode::first();

    if (!empty($verification_code) && $verification_code->code == $request->input('code')) {

      $user_id = $verification_code->user_id;
      VerifyCode::truncate();
      if (Auth::loginUsingId($user_id)) {

        $name = Auth::user()->firstname . ' ' . Auth::user()->lastname;
        $login = Auth::user()->group;
        \Session::put('name', $name);
        \Session::put('userRole', $login);
        $institute = Institute::select('name')->first();
        if (!$institute) {
          if (Auth::user()->group != "Admin") {
            return Redirect::to('/verify_code')
              ->withInput($request->all())->with('error', 'Institute Information not setup yet!Please contact administrator.');
          } else {
            $institute = new Institute;
            $institute->name = "IctVission";
            \Session::put('inName', $institute->name);
            return Redirect::to('/institute')->with('error', 'Please provide institute information!');
          }
        } else {
          \Session::put('inName', $institute->name);
          return Redirect::to('/dashboard')->with('success', 'You are now logged in.');
        }
      }
    } else {
      return Redirect::to('/verify_code')
        ->withInput($request->all())->with('error', 'Code Not Match please enter Correct Code');
    }
  }

  public function getLogout()
  {
    /*request()->session()->flush();
    \Auth::logout();*/

    if (request()->session()->pull('isAdmin', 0)) {
      $id = request()->session()->pull('adminID', 0);
      //$url = request()->session()->pull('surl','');
      //$id = request()->session()->pull('adminID', 0);
      if (Auth::loginUsingId($id)) {
        //request()->session()->flush();
        $name  = Auth::user()->firstname . ' ' . Auth::user()->lastname;
        $login = Auth::user()->group;
        \Session::put('name', $name);
        \Session::put('userRole', $login);
        return redirect('/dashboard');
      }
      return redirect('/dashboard');
    }
    request()->session()->flush();
    \Auth::logout();
    return redirect('/')->with('message', 'Your are now logged out!');
  }
  public function dologin($id)
  {
    // Only a signed-in administrator may assume another identity. Without these
    // checks this route is an unauthenticated login-as-anyone bypass.
    if (!Auth::check() || Auth::user()->group !== 'Admin') {
      abort(403);
    }

    $user = User::find($id);
    if (!$user) {
      abort(404);
    }

    // The account to return to is the real signed-in admin, never a value
    // supplied in the URL: getLogout() passes it back to Auth::loginUsingId().
    $adminId = Auth::id();

    request()->session()->forget('isAdmin');
    request()->session()->forget('adminID');
    request()->session()->forget('surl');
    request()->session()->put('isAdmin', 1);
    request()->session()->put('adminID', $adminId);

    // echo request()->root();
    //echo "<pre>rr".request()->session()->get('adminID')."tt";print_r($user);
    if (Auth::loginUsingId($id)) {

      $name  = Auth::user()->firstname . ' ' . Auth::user()->lastname;
      $login = Auth::user()->group;
      \Session::put('name', $name);
      \Session::put('userRole', $login);
      //echo "adeel";
      return redirect('/dashboard');
    }
  }

  public  function show()
  {
    //User::create(array('firstname'=>'Mr.','lastname'=>'kashif','login'=>'ictkashif','email' => 'kashif@ictinnovations.com','group'=>'Admin','desc'=>'admin Deatils Here',"password"=> Hash::make("123456")));
    $users = User::all();
    $user = array();
    //return View::Make('app.users',compact('users','user'));
    return View('app.users', compact('users', 'user'));
  }
  public function create(Request $request)
  {
    $data = $request->validate([
      'firstname' => ['required', 'string', 'max:100'],
      'lastname' => ['required', 'string', 'max:100'],
      'email' => ['required', 'email', 'max:190', 'unique:users,email'],
      'login' => ['required', 'string', 'max:100', 'unique:users,login'],
      'password' => ['required', 'string', 'min:8', 'confirmed'],
      'group' => ['required', 'in:Director,Admin,Teacher,Accountant,Staff,Other'],
      'desc' => ['nullable', 'string', 'max:1000'],
      'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
    ]);

    $user = new User();
    $user->firstname = $data['firstname'];
    $user->lastname = $data['lastname'];
    $user->login = $data['login'];
    $user->email = $data['email'];
    $user->group = $data['group'];
    $user->desc = $data['desc'] ?? '';
    $user->password = Hash::make($data['password']);

    if ($request->hasFile('avatar')) {
      $user->avatar = $request->file('avatar')->store('avatars', 'public');
    }

    $user->save();

    return Redirect::to('/users')->with('success', 'تم إنشاء المستخدم بنجاح.');
  }

  public function edit($id)
  {
    $user = User::find($id);
    $users = User::all();
    //return View::Make('app.users',compact('users','user'));
    return View('app.users', compact('users', 'user'));
  }
  public function update(Request $request)
  {
    $user = User::findOrFail($request->input('id'));

    $data = $request->validate([
      'id' => ['required', 'integer'],
      'firstname' => ['required', 'string', 'max:100'],
      'lastname' => ['required', 'string', 'max:100'],
      'email' => ['required', 'email', 'max:190', 'unique:users,email,' . $user->id],
      'login' => ['required', 'string', 'max:100', 'unique:users,login,' . $user->id],
      'group' => ['required', 'in:Director,Admin,Teacher,Accountant,Staff,Other'],
      'desc' => ['nullable', 'string', 'max:1000'],
      'password' => ['nullable', 'string', 'min:8', 'confirmed'],
      'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
    ]);

    // Never allow the current platform owner to be demoted or renamed away from Admin.
    if (strtolower((string) $user->email) === strtolower((string) env('OWNER_EMAIL', 'Safwt771754091s@gmail.com'))) {
      $data['group'] = 'Admin';
    }

    $user->firstname = $data['firstname'];
    $user->lastname = $data['lastname'];
    $user->login = $data['login'];
    $user->email = $data['email'];
    $user->group = $data['group'];
    $user->desc = $data['desc'] ?? '';

    if ($request->hasFile('avatar')) {
      if (!empty($user->avatar)) {
        Storage::disk('public')->delete($user->avatar);
      }
      $user->avatar = $request->file('avatar')->store('avatars', 'public');
    }

    if (!empty($data['password'])) {
      $user->password = Hash::make($data['password']);
    }

    $user->save();

    return Redirect::to('/users')->with('success', 'تم تحديث المستخدم بنجاح.');
  }

  public function delete($id)
  {
    $user = User::findOrFail($id);

    if ((int) $user->id === (int) Auth::id()) {
      return Redirect::to('/users')->with('error', 'لا يمكنك حذف الحساب الذي تستخدمه حالياً.');
    }

    if (strtolower((string) $user->email) === strtolower((string) env('OWNER_EMAIL', 'Safwt771754091s@gmail.com'))) {
      return Redirect::to('/users')->with('error', 'لا يمكن حذف مالك المنصة.');
    }

    if (!empty($user->avatar)) {
      Storage::disk('public')->delete($user->avatar);
    }

    $user->delete();

    return Redirect::to('/users')->with('success', 'تم حذف المستخدم بنجاح.');
  }

  public function generateCode($codeLength = 4)
  {
    $min = pow(10, $codeLength);
    $max = $min * 10 - 1;
    $code = mt_rand($min, $max);

    return $code;
  }

  public function session(Request $request)
  {

    // $request->session()->flash('success', 'This is a success message');
    // return view('alert');

    return Redirect::to('/')->with('message', 'This is a success message222');
    return view('alert')->with('message', 'This is a success message 2222');
  }
}
