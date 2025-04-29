<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Admin;
use App\Models\Advisor;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    // แสดงรายการผู้ดูแลระบบทั้งหมด
    public function index()
    {
        $admins = Admin::all();
        return view('admins.index', compact('admins'));
    }

    // แสดงแดชบอร์ดของแอดมิน
    public function dashboard()
    {
        // นับจำนวนนักศึกษาทั้งหมด และแยกตามเพศ
        $totalStudents = Student::count();
        $maleStudents = Student::where('gender', 'Male')->count();
        $femaleStudents = Student::where('gender', 'Female')->count();

        // นับจำนวนที่ปรึกษา
        $totalAdvisors = Advisor::count();

        // นับจำนวนกิจกรรมตามสถานะ
        $approvedAppointments = Activity::where('status', 'Approved')->count();
        $rejectedAppointments = Activity::where('status', 'Rejected')->count();
        $pendingAppointments = Activity::where('status', 'Pending')->count();

        return view('admin.dashboard', compact(
            'totalStudents', 
            'maleStudents', 
            'femaleStudents', 
            'totalAdvisors', 
            'approvedAppointments', 
            'rejectedAppointments',
            'pendingAppointments'
        ));
    }

    // แสดงฟอร์มสร้างแอดมินใหม่
    public function create()
    {
        return view('admins.create');
    }
    
    // แสดงปฏิทินนัดหมายของนักศึกษาและที่ปรึกษา
    public function calendar(Request $request)
    {
        // ดึงข้อมูลกิจกรรมทั้งหมด พร้อมความสัมพันธ์กับนักศึกษาและที่ปรึกษา
        $query = Activity::with(['student', 'advisor']);
        
        // กรองตามสถานะ ถ้าผู้ใช้มีการกรอกค่ามา
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // กรองตามช่วงเวลา ถ้าผู้ใช้เลือกวันที่
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('meeting_date', [$request->input('date_from'), $request->input('date_to')]);
        }
    
        // แสดงข้อมูลแบบแบ่งหน้า
        $activities = $query->paginate(10);
    
        // นับจำนวนแต่ละสถานะทั้งหมด
        $pendingCount = Activity::where('status', 'Pending')->count();
        $approvedCount = Activity::where('status', 'Approved')->count();
        $rejectedCount = Activity::where('status', 'Rejected')->count();
    
        return view('meetStudentWithAdvisor', [
            'activities' => $activities,
            'pendingCount' => $pendingCount,
            'approvedCount' => $approvedCount,
            'rejectedCount' => $rejectedCount,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
        ]);
    }
    
    // บันทึกข้อมูลแอดมินใหม่
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:admins', // ต้องไม่ซ้ำ
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:8',
        ]);

        $admin = new Admin($request->all());
        $admin->password = bcrypt($request->password); // เข้ารหัสรหัสผ่านก่อนบันทึก
        $admin->save();

        return redirect()->route('admins.index')->with('success', 'Admin created successfully');
    }

    // แสดงฟอร์มแก้ไขข้อมูลแอดมิน
    public function edit($id)
    {
        $admin = Admin::findOrFail($id); // ค้นหาข้อมูลแอดมินจาก ID
        return view('admins.edit', compact('admin'));
    }

    // อัปเดตข้อมูลแอดมิน
    public function update(Request $request, $id)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:admins,username,' . $id,
            'email' => 'required|email|unique:admins,email,' . $id,
            'password' => 'nullable|string|min:8', // รหัสผ่านเป็นตัวเลือก (หากไม่ได้กรอกจะไม่เปลี่ยน)
        ]);

        $admin = Admin::findOrFail($id);
        $admin->update($request->except('password')); // อัปเดตข้อมูลทั่วไป

        // ถ้ามีการเปลี่ยนรหัสผ่าน
        if ($request->password) {
            $admin->password = bcrypt($request->password); // เข้ารหัสรหัสผ่านก่อนบันทึก
            $admin->save();
        }

        return redirect()->route('admins.index')->with('success', 'Admin updated successfully');
    }

    // ลบข้อมูลแอดมิน
    public function destroy($id)
    {
        $admin = Admin::findOrFail($id);
        $admin->delete();

        return redirect()->route('admins.index')->with('success', 'Admin deleted successfully');
    }

    // ออกจากระบบแอดมิน
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout(); // ออกจากระบบสำหรับแอดมิน
        $request->session()->invalidate(); // ทำให้เซสชันใช้งานไม่ได้
        $request->session()->regenerateToken(); // ป้องกัน CSRF
        return redirect('/');
    }
}
