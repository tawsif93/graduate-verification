<?php

namespace App\Http\Controllers\Auth;

use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use App\Registrar;
use App\ProgramOffice;
use App\User_activation;
use Mail;
use App\Mail\EmailVesrification;
use App\SMS\SMSManager;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

/*    use RegistersUsers;*/

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('role:UGC,SystemAdmin,Registrar')->only([
            'showRegistrationForm',
            'storeUser'
        ]);

        $this->middleware('guest')->only(['showActivationForm', 'userActivate',
            'showSendActivationCodeForm', 'activationCodeSend', 'sendActivationCode']);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    /*protected function validator(array $data)
    {
        return Validator::make($data, [
            'first_name' => 'required|string|max:20',
            'last_name' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:users',
            'mobile_no' => 'required|string|max:11|unique:users',
        ]);
    }
*/
    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    /*protected function create(array $data)
    {
        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'mobile_no' => $data['mobile_no'],

        ]);
    }*/

    public function showRegistrationForm(Request $request)
    {
        if($request->user()->role == "SystemAdmin"){
            $roles = ['UGC' => 'UGC', 'Registrar' => 'Registrar', 'ProgramOffice' => 'ProgramOffice'];
        }
        elseif ($request->user()->role == "UGC") {
            $roles = ['Registrar' => 'Registrar'];
        }
        elseif ($request->user()->role == "Registrar") {
            $roles = ['ProgramOffice' => 'ProgramOffice'];
        }
        return view('user.create', ['roles' => $roles]);
    }

    public function storeUser(Request $request){

        $this->validate($request, [
            'first_name' => 'required|string|max:20',
            'last_name' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:user',
            'mobile_no' => 'required|string|max:11',
            'role' => 'required',
        ]);

        $user = new User;
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->email = $request->email;
        $user->mobile_no = $request->mobile_no;
        $user->role = $request->role;
        $user->is_activated = false;

        $role_name = $user->role;

        if($role_name == 'Registrar'){
            $this->validate($request, [
                'university_id' => 'required',
            ]);

            $registrar = new Registrar;
            $registrar->university_id = $request->university_id;

            $user->save();
            $registrar->user_id = $user->id;
            $registrar->save();
        }

        else if($role_name == 'ProgramOffice'){
            $this->validate($request, [
                'department_id' => 'required',
            ]);

            $program_office = new ProgramOffice;
            $program_office->department_id = $request->department_id;

            $user->save();
            $program_office->user_id = $user->id;
            $program_office->save();
        }
        else{
            $user->save();
        }

        $this->sendActivationCode($user);

        flash('User successfully added!')->success();

        return redirect()->route('user.create');

    }


    public function showActivationForm(){
        return view('auth.user_activation');
    }


    public function userActivate(Request $request){
        $this->validate($request, [
            'email' => 'required|string|email|max:255',
            'activation_code' => 'required|integer',
        ]);

        $user = User::where('email', $request->email)->first();
        if($user == null){
            flash('There is no user with your email!')->error();
            return redirect()->route('user.activation');
        }
        if($user->is_activated){
            flash('Your account already is activated!')->warning();
            return redirect()->route('user.activation');
        }

        $user_activation = $user->user_activation;
        if($user_activation==null || $user_activation->token!=$request->activation_code || strtotime($user_activation->created_at) + 60*60*24 < time()){
            flash('Invalid activation code.Check your email and mobile phone for activation code. Or resend activation code')->error();
            return redirect()->route('user.activation');

        }

        return redirect()->route('user.reset_password',[ $user->email, $user_activation->token]);

    }

    public function showSendActivationCodeForm(){
        return view('auth.activation_code_send');
    }

    public function activationCodeSend(Request $request){
        $this->validate($request, [
            'email' => 'required|string|email|max:255',
        ]);

        $user = User::where('email', $request->email)->first();
        if($user == null){
            flash('There is no user with your email!')->error();
            return redirect()->route('user.send_activation_code');
        }
        $user->is_activated = false;
        $user->save();

        $this->sendActivationCode($user);
        flash('Activation code has been successfully sent to your mail and mobile!');
        return redirect()->route('user.activation');

    }


    public function sendActivationCode($user)
    {
        $user_activation = ($user->user_activation==null)? new User_activation: $user->user_activation;
        $activation_code = rand(100000, 999999);
        $user_activation->user_id = $user->id;
        $user_activation->token = $activation_code;
        $user_activation->save();

        $array=['name' => $user->first_name, 'token' => $activation_code];
        Mail::to($user->email)->queue(new EmailVerification($array));

        $smsBody = 'Welcome, '.$user->first_name.' Your Activation code is '.$activation_code.'. Please activate your account http://127.0.0.1/user/activation. Thank You. ';
        $smsManager = new SMSManager();
        //$smsManager->sendSMS($user->mobile_no, $smsBody);

    }

}
