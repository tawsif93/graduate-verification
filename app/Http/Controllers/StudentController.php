<?php

namespace App\Http\Controllers;

use App\Marks;
use Illuminate\Http\Request;
use App\User;
use App\Student;
use App\Department;
use App\University;
use App\Stakeholder;
use App\Verification;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Mail;
use App\Mail\EmailManager;
use App\SMS\SMSManager;

class StudentController extends Controller
{
    public function __construct()
    {
        
        $this->middleware('role:Registrar, SystemAdmin')->only([
            'showStudentCreateForm',
            'storeStudent',
            'showStudentView',
            'verifyStudent'
        ]);

        $this->middleware('guest')->only([
            'storePaymentRequest',
            'paymentRequestView'
        ]);

        $this->middleware('')->only([

            
        ]);
    }

    public function showStudentCreateForm(){
        return view('student.create');
    }

    public function searchStudentView() {
        return view('stakeholder.search_student');
    }

    public function searchStudent(Request $request) {

        $request->date_of_birth = date('Y-m-d', strtotime($request->date_of_birth));

        $student_info = Student::where('registration_no', $request->registration_no)->first();
        if($student_info == null) {
            flash('Registration number does not exist');
            return redirect()->route('stakeholder.search');
        }
        $user_info = User::where('id', $student_info->user_id)->first();
        if($user_info == null) {
            flash('User does not exist');
            return redirect()->route('stakeholder.search');
        }

        if ($user_info->email != $request->email) {
            flash('Email does not match with the Registration Number');
            return redirect()->route('stakeholder.search');
        }
        if ($student_info->date_of_birth != $request->date_of_birth) {
            flash('Date of Birth does not match with the registration number');
            return redirect()->route('stakeholder.search');
        }
        if ($student_info->department->university->id != $request->university_id) {
            flash('University name does not match with the registration number');
            return redirect()->route('stakeholder.search');
        }
        return redirect()->route('stakeholder.payment_request', ['registration_no' => $student_info->registration_no]);
    }

    public function paymentRequestView(Request $request, $registration_no) {
        $student_info = Student::where('registration_no', $registration_no)->first();
        $user_info = User::where('id', $student_info->user_id)->first();
        $university_info = University::where('id', $student_info->department->university->id)->first();
        return view('stakeholder.payment_request', [
            'student' => $student_info,
            'user' => $user_info,
            'university' => $university_info]);
    }

    public function storePaymentRequest(Request $request, $registration_no) {
        $this->validate($request, [
            'name' => 'required|string|max:30',
            'institute' => 'required|string|max:50',
            'designation' => 'required|string|max:20',
            'email' => 'required|email|max:30',
            'country' => 'required|string|max:30'
        ]);

        $student = Student::where('registration_no', $registration_no)->first();

        if($student == null) {
            flash('User not found');
            return redirect()->route('stakeholder.search');
        }

        $stakeholder = new Stakeholder;
        $stakeholder->name = $request->name;
        $stakeholder->institute = $request->institute;
        $stakeholder->email = $request->email;
        $stakeholder->designation = $request->designation;
        $stakeholder->country = $request->country;

        $stakeholder->save();

        $verification = new Verification;
        $verification->student_id = $student->id;
        $verification->stakeholder_id = $stakeholder->id;
        $verification->verification_status = "Requested";
        $verification->save();

        $array= $verification->stakeholder->name.' has requested to verify '.$verification->student->user->first_name.' of '.$verification->student->department->name.' of '.$verification->student->department->university->name.' (Registration no: '. $verification->student->registration_no.'). Please go through the following link to pay the verification fee. http://127.0.0.1/payment/verification/'.$verification->id;

        Mail::to($verification->student->user->email)->queue(new EmailManager($array));
        Mail::to($verification->stakeholder->email)->queue(new EmailManager($array));

        $smsManager = new SMSManager();
        $smsManager->sendSMS($student->user->mobile_no, $array);

        flash('Successfully requested!')->success();

        return redirect()->route('stakeholder.search');

    }

    public function storeStudent(Request $request){

        $this->validate($request, [
            'first_name' => 'required|string|min:3|max:20',
            'last_name' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:user',
            'mobile_no' => 'required|string|min:11|max:11',
            'university_id' => 'required|integer',
            'department_id' => 'required|integer',
            'date_of_birth' => 'required|date',
            'registration_no' => 'required|string|unique_with:student,department_id',
            'session_no' => 'required|string|min:7|max:7'
        ]);

        $user = new User;
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->email = $request->email;
        $user->mobile_no = $request->mobile_no;
        $user->role = "Student";
        $user->is_activated = false;

        $user->save();

        $student = new Student;
        $student->user_id = $user->id;
        $student->department_id = $request->department_id;
        $student->registration_no = $request->registration_no;
        $student->session = $request->session_no;
        $student->date_of_birth =date('Y-m-d', strtotime($request->date_of_birth));

        $student->save();

        $registerController = new RegisterController();
        $registerController->sendActivationCode($user);

        flash('Student successfully added!')->success();

        return redirect()->route('student.create');

    }

    function getDynamicReportStudentData(Request $request) {
        $students = Student::all();
        if($request->department_id)
            $students = $students->where('department_id', $request->department_id);
        if($request->session_no)
            $students = $students->where('session', $request->session_no);

        $ids = $students->pluck('id');
        $filtered = $students->whereIn('id', $ids);

        return array(
            'num_of_student' => $students->count(),
            'verification_request' => $filtered->where('verification_status', 'Requested')->count(),
            'verification_process' => $filtered->where('verification_status', 'In Progress')->count(),
            'verified' => $filtered->where('verification_status', 'In Progress')->count()
        );
    }


    public function showStudentView() {

        return view('student.view');
    }

    public function getStudentListByDepartment(Request $request){

        $page_count = 10;

        $students = Student::select('student.id',DB::raw("CONCAT(user.first_name,' ',user.last_name) as full_name"), 'student.session', 'student.registration_no', 'student.date_of_birth', 'user.email', 'user.mobile_no')->join('user', 'user.id', '=', 'student.user_id')->where('department_id', $request->department_id)->paginate($page_count);

        $theads = array('Student Name', 'Session', 'Registration No', 'Date of Birth', 'Email', 'Mobile No');

        $properties = array('full_name', 'session', 'registration_no', 'date_of_birth', 'email', 'mobile_no');

        return view('partials._table',['theads' => $theads, 'properties' => $properties, 'tds' => $students])->with('i', ($request->input('page', 1) - 1) * $page_count);
    }


    public function verifyStudentView(Request $request, $id) {
        $student = Verification::where('id', $id)->first()->student;
        $marks = Marks::where('student_id', $student->id)->get();
        $all_marks = array();

        $num_of_semester = $student-> department -> num_of_semester;
        for($sem = 1; $sem <= $num_of_semester; $sem++) {
            $semester_marks = array();
            foreach ($marks as $mark)
                if($mark -> course -> semester_no == $sem)
                    $semester_marks[] = $mark;
            $all_marks[] = $semester_marks;
        }

        $cum_points = 0;
        $cum_credit = 0;
        $gpa = array();

        foreach ($all_marks as $marks) {

            $point_sum = 0;
            $credit_sum = 0;

            foreach ($marks as $mark) {

                $point_sum += $mark->course->credit * $mark->gpa;
                $credit_sum += $mark->course->credit;

            }

            if($credit_sum <= 0.0) $gpa[] = -1;
            else $gpa[] = $point_sum / $credit_sum;

            $cum_points += $point_sum;
            $cum_credit += $credit_sum;

        }

        if($cum_credit <= 0.0) $cgpa = -1;
        else $cgpa = $cum_points / $cum_credit;

        return view('student.verify',
            [
                'verification_id' => $id,
                'student' => $student,
                'all_marks' => $all_marks,
                'gpa' => $gpa,
                'cgpa' => $cgpa
            ]);
    }

    public function verifyStudentPublicView(Request $request, $hash) {
        $verification = Verification::where('hash', $hash)->first();
        $student = $verification->student;
        $marks = Marks::where('student_id', $student->id)->get();
        $all_marks = array();

        $num_of_semester = $student-> department -> num_of_semester;
        for($sem = 1; $sem <= $num_of_semester; $sem++) {
            $semester_marks = array();
            foreach ($marks as $mark)
                if($mark -> course -> semester_no == $sem)
                    $semester_marks[] = $mark;
            $all_marks[] = $semester_marks;
        }

        $cum_points = 0;
        $cum_credit = 0;
        $gpa = array();

        foreach ($all_marks as $marks) {

            $point_sum = 0;
            $credit_sum = 0;

            foreach ($marks as $mark) {

                $point_sum += $mark->course->credit * $mark->gpa;
                $credit_sum += $mark->course->credit;

            }

            if($credit_sum <= 0.0) $gpa[] = -1;
            else $gpa[] = $point_sum / $credit_sum;

            $cum_points += $point_sum;
            $cum_credit += $credit_sum;

        }

        if($cum_credit <= 0.0) $cgpa = -1;
        else $cgpa = $cum_points / $cum_credit;

        return view('student.verify_public',
            [
                'verification_id' => $verification->id,
                'student' => $student,
                'all_marks' => $all_marks,
                'gpa' => $gpa,
                'cgpa' => $cgpa,
                'sign_link' => $verification->digital_sign,
                'hash' => $verification->hash
            ]);
    }

    function verifyStudent(Request $request, $id) {
        $path = $request->file('signature')->store('public/signatures');

        $verification = Verification::where('id', $id)->first();
        $verification->digital_sign = substr($path,7);
        $verification->verification_status = 'Verified';
        $verification->hash = bcrypt($verification->digital_sign);
        $verification->save();


        $array= $verification->student->user->first_name.' of '.$verification->student->department->name.' of the '.$verification->student->department->university->name.' (Registration no: '.$verification->student->registration_no.' has been verified requested to verify by '.$verification->stakeholder->name.' Please visit the following link to check http://127.0.0.1:8000/student/verify/public/'.$verification->hash ;

        Mail::to($verification->student->user->email)->queue(new EmailManager($array));
        Mail::to($verification->stakeholder->email)->queue(new EmailManager($array));

        $smsManager = new SMSManager();
        $smsManager->sendSMS($verification->student->user->mobile_no, $array);

        return redirect()->route('student.verify', $id);
    }

    public function show(Request $request, $id){
        $student = Student::select('user.first_name', 'user.last_name', 'student.department_id', 'student.session', 'student.registration_no', 'student.date_of_birth', 'user.email', 'user.mobile_no')->join('user', 'user.id', '=', 'student.user_id')->where('student.id', $id)->first();
        $department = Department::find($student->department_id);
        
        return view('student.show', ['student' => $student, 'department' => $department]);
    }

    public function edit(Request $request, $id){
        $student = Student::find($id);
        $user = $student->user;
        return view('student.edit', ['student' => $student, 'user' => $user]);
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'session' => 'required|string|max:255',
            'registration_no' => 'required|string|max:255',

        ]);


        $student = Student::find($id);
        $student->session = $request->session;
        $student->registration_no = $request->registration_no;
        $student->user->first_name = $request->first_name;
        $student->user->last_name = $request->last_name;
        $student->user->save();
        $student->save();

        $url = $request->input('url');

        flash('Student updated successfully')->success();

        return redirect($url);

    }

    public function destroy(Request $request, $id)
    {
        $student = Student::find($id);
        $user_id = $student->user_id;
        $url = $request->input('url');
        
        try {
            if(count($student->marks)==0){
                $student->marks->delete();
                $student->delete();
                User::find($user_id)->delete();    
            }
            $student->delete();
            User::find($user_id)->delete();    
        }catch(\Exception $e){
            flash('The student cannot be deleted!')->error();
            return redirect()->back();
        }

        flash('Student deleted successfully');

        return redirect()->back();
    }

}
