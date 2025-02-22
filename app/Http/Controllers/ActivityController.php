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
    public function index()
    {
        // Get the currently logged-in student
        $student = Auth::guard('student')->user();

        // Retrieve activities for the logged-in student
        $activities = Activity::with(['student', 'advisor'])
            ->where('student_id', $student->id) // Retrieve activities for this specific student
            ->get();

        // Debug the activities to make sure data is coming through
        // dd($activities);

        // Pass the activities to the view
        return view('student.meet', ['activities' => $activities]);
    }







    public function create(Request $request)
    {
        // Using the 'student' guard to get the authenticated student
        $student = Auth::guard('student')->user();

        // // Debug: Inspect the student object
        // dd($student);

        if (!$student || !$student->advisor) {
            return redirect()->back()->withErrors(['advisor_id' => 'No advisor assigned. Please contact the administrator.']);
        }

        $meeting_date = $request->query('meeting_date');

        return view('student.createmeet', [
            'students' => Student::all(),
            'advisors' => Advisor::all(),
            'meeting_date' => $meeting_date,
        ], compact('student'));
    }

    // Store a new activity
    public function store(Request $request)
    {
        // Validate the request
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'advisor_id' => 'nullable|exists:advisors,id', // Allow null if no advisor is assigned
            'meeting_date' => 'required|date',
            'discussion_content' => 'required|string',
            'evidence.*' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Gather form data
        $data = $request->all();

        // If no advisor is selected, set it to the advisor of the logged-in student
        if (empty($data['advisor_id']) && Auth::user()->advisor) {
            $data['advisor_id'] = Auth::user()->advisor->id;
        }

        // Handle evidence upload
        $evidence = [];
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $path = $file->store('evidence', 'public');
                $evidence[] = $path;
            }
        }
        $data['evidence'] = json_encode($evidence); // Save as JSON

        // Create the new activity (meeting)
        Activity::create($data);

        // Redirect with success message
        return redirect()->route('meet.meet')->with('success', 'Activity created successfully');
    }

//     public function editFORadvisor($id)
// {
//     $activity = Activity::findOrFail($id);  // หาข้อมูลกิจกรรมที่ต้องการแก้ไข
//     return view('advisor.meet', compact('activity'));  // ส่งข้อมูลกิจกรรมไปยัง view
// }



    public function edit($id)
    {
        // Find the activity by its ID
        $activity = Activity::findOrFail($id);

        // Get the currently logged-in student
        $student = Auth::guard('student')->user(); // Use 'student' instead of 'loggedInStudent'

        // Get lists of all students and advisors for dropdown options
        $students = Student::all();
        $advisors = Advisor::all();
        // Decode existing evidence if any
        $evidence = json_decode($activity->evidence, true) ?? [];
        // Pass the data to the view
        return view('student.editAppointment', compact('activity', 'students', 'advisors', 'student', 'evidence'));
    }

    public function update(Request $request, $id)
    {
        try {
            // Validate the incoming request data
            $request->validate([
                'meeting_date' => 'required|date',
                'discussion_content' => 'required|string',
                'evidence.*' => 'nullable|image|mimes:jpg,jpeg,png|max:2048', // Validate image files
            ]);
    
            // Find the activity by its ID
            $activity = Activity::findOrFail($id);
    
            // Handle evidence upload (existing evidence and new uploads)
            $evidence = json_decode($activity->evidence, true) ?? [];
    
            if ($request->hasFile('evidence')) {
                foreach ($request->file('evidence') as $file) {
                    $path = $file->store('evidence', 'public');
                    $evidence[] = $path; // Add new file to the evidence array
                }
            }
    
            // Update the evidence field in the database
            $activity->evidence = json_encode($evidence);
    
            // Update other fields
            $activity->meeting_date = $request->input('meeting_date');
            $activity->discussion_content = $request->input('discussion_content');
    
            // Save the changes
            $activity->save();
    
            // Redirect with success message
            return redirect()->route('meet.meet')->with('success', 'Activity updated successfully');
    
        } catch (\Exception $e) {
            // If there's an exception, return it as JSON
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
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
    

    // Delete an existing activity
    public function destroy($id)
    {
        $activity = Activity::findOrFail($id);

        // Delete evidence files
        if ($activity->evidence) {
            $evidence = json_decode($activity->evidence, true);
            foreach ($evidence as $file) {
                Storage::disk('public')->delete($file);
            }
        }

        $activity->delete();

        return redirect()->route('meet.meet')->with('success', 'Activity deleted successfully');
    }
}
