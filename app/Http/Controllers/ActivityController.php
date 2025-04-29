<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Advisor;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ActivityController extends Controller
{
    // แสดงกิจกรรมของนักศึกษาที่ล็อกอินอยู่
    public function index()
    {
        $student = Auth::guard('student')->user(); // รับข้อมูลนักศึกษาที่ล็อกอินอยู่

        // ดึงกิจกรรมที่เป็นของนักศึกษาคนนั้น
        $activities = Activity::with(['student', 'advisor'])
            ->where('student_id', $student->id)
            ->get();

        // ส่งข้อมูลไปยังหน้า student.meet
        return view('student.meet', ['activities' => $activities]);
    }

    // แสดงหน้าแบบฟอร์มสร้างกิจกรรมใหม่
    public function create(Request $request)
    {
        $student = Auth::guard('student')->user(); // รับข้อมูลนักศึกษาที่ล็อกอินอยู่

        // ตรวจสอบว่านักศึกษามีที่ปรึกษาหรือไม่
        if (!$student || !$student->advisor) {
            return redirect()->back()->withErrors(['advisor_id' => 'No advisor assigned. Please contact the administrator.']);
        }

        $meeting_date = $request->query('meeting_date'); // รับค่าวันที่ประชุมจาก query parameter

        // ส่งข้อมูลไปยังหน้า student.createmeet
        return view('student.createmeet', [
            'students' => Student::all(),
            'advisors' => Advisor::all(),
            'meeting_date' => $meeting_date,
        ], compact('student'));
    }

    // บันทึกกิจกรรมใหม่ลงฐานข้อมูล
    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'advisor_id' => 'nullable|exists:advisors,id', // อนุญาตให้เป็น null ได้
            'meeting_date' => 'required|date',
            'discussion_content' => 'required|string',
            'evidence.*' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $data = $request->all();

        // หากไม่ได้เลือกอาจารย์ที่ปรึกษา ให้ใช้ที่ปรึกษาของนักศึกษาที่ล็อกอินอยู่
        if (empty($data['advisor_id']) && Auth::user()->advisor) {
            $data['advisor_id'] = Auth::user()->advisor->id;
        }

        // อัปโหลดหลักฐาน (ไฟล์รูปภาพ)
        $evidence = [];
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $path = $file->store('evidence', 'public');
                $evidence[] = $path;
            }
        }
        $data['evidence'] = json_encode($evidence); // บันทึกเป็น JSON

        // สร้างกิจกรรมใหม่
        Activity::create($data);

        return redirect()->route('meet.meet')->with('success', 'Activity created successfully');
    }

    // แสดงแบบฟอร์มแก้ไขกิจกรรม
    public function edit($id)
    {
        $activity = Activity::findOrFail($id); // ค้นหากิจกรรมจาก ID
        $student = Auth::guard('student')->user(); // รับข้อมูลนักศึกษาที่ล็อกอินอยู่

        $students = Student::all();
        $advisors = Advisor::all();
        $evidence = json_decode($activity->evidence, true) ?? []; // แปลง JSON กลับเป็น array

        return view('student.editAppointment', compact('activity', 'students', 'advisors', 'student', 'evidence'));
    }

    // อัปเดตกิจกรรมที่มีอยู่
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'meeting_date' => 'required|date',
                'discussion_content' => 'required|string',
                'evidence.*' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            $activity = Activity::findOrFail($id);

            // จัดการอัปโหลดไฟล์หลักฐาน (เพิ่มไฟล์ใหม่)
            $evidence = json_decode($activity->evidence, true) ?? [];
            if ($request->hasFile('evidence')) {
                foreach ($request->file('evidence') as $file) {
                    $path = $file->store('evidence', 'public');
                    $evidence[] = $path;
                }
            }
            $activity->evidence = json_encode($evidence);

            // อัปเดตข้อมูลอื่น ๆ
            $activity->meeting_date = $request->input('meeting_date');
            $activity->discussion_content = $request->input('discussion_content');

            $activity->save(); // บันทึกข้อมูล

            return redirect()->route('meet.meet')->with('success', 'Activity updated successfully');

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // อัปเดตกิจกรรมโดยอาจารย์ที่ปรึกษา
    public function updateForAdvisor(Request $request, $id)
    {
        $request->validate([
            'advisor_comment' => 'required|string|max:500',
            'status' => 'required|in:Pending,Approved,Rejected',
        ]);

        $activity = Activity::findOrFail($id);
        $activity->update([
            'advisor_comment' => $request->input('advisor_comment'),
            'status' => $request->input('status'),
        ]);

        return redirect()->route('calendar.meet')->with('success', 'Activity updated successfully by Advisor');
    }

    // ลบกิจกรรม
    public function destroy($id)
    {
        $activity = Activity::findOrFail($id);

        // ลบไฟล์หลักฐานที่เกี่ยวข้อง
        if ($activity->evidence) {
            $evidence = json_decode($activity->evidence, true);
            foreach ($evidence as $file) {
                Storage::disk('public')->delete($file);
            }
        }

        $activity->delete(); // ลบกิจกรรม

        return redirect()->route('meet.meet')->with('success', 'Activity deleted successfully');
    }
}
