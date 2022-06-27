<?php

namespace App\Http\Controllers\Student;

use App\Models\User;
use App\Models\Borrow;
use App\Models\Student;
use App\Models\Document;
use Illuminate\Http\Request;
use App\Services\MediaService;
use App\Exports\StudentsExport;
use App\Imports\StudentsImport;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $students = Student::with('faculty:id,name')->paginate(10);

        return response()->json($students);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize(Student::class);
        $data = $request->validate([
            'name'          => ['required'],
            'phone_no'      => ['required', 'integer', 'digits:10', 'regex:/((98)|(97))(\d){8}/'],
            'address'       => ['required'],
            'email'         => ['required', 'email', 'unique:students,email'],
            'college_email' => ['required', 'email', 'unique:students,college_email'],
            'faculty_id'    => ['required', 'exists:faculties,id'],
            'parent_name'   => ['required'],
            'parent_contact' => ['required', 'integer', 'digits:10', 'regex:/((98)|(97))(\d){8}/'],
            'year'           => ['required', 'integer', 'min:' . config('app.year')],
            'image'          => ['nullable', 'image', 'mimes:jpeg,png,gif'],
            'registration_no' => ['nullable'],
            'symbol_no'      => ['nullable'],
            'password' => ['required', 'min:6'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = MediaService::upload($request->file('image'), "students");
        }
        $data['password'] = bcrypt($request->password);
        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['college_email'],
                'password' => $data['password'],
                'role_id' => 4,
            ]);
            $data['user_id'] = $user->id;
            Student::create($data);
        });

        return response()->json(['message' => 'Student has been Created Successfully!', 'student' => $data], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Student  $student
     * @return \Illuminate\Http\Response
     */
    public function show(Student $student)
    {
        $this->authorize($student);
        $student->load(['faculty:id,name', 'borrows']);

        return response()->json($student);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Student  $student
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Student $student)
    {
        $this->authorize($student);
        $data = $request->validate([
            'name'          => ['nullable'],
            'phone_no'      => ['nullable', 'integer', 'digits:10', 'regex:/((98)|(97))(\d){8}/'],
            'address'       => ['nullable'],
            'email'         => ['nullable', 'email', 'unique:students,email'],
            'college_email' => ['required', 'email', 'unique:students,college_email'],
            'faculty_id'    => ['nullable', 'exists:faculties,id'],
            'parent_name'   => ['nullable'],
            'parent_contact' => ['required', 'integer', 'digits:10', 'regex:/((98)|(97))(\d){8}/'],
            'year'           => ['nullable', 'integer', 'min:' . config('app.year')],
            'image'          => ['nullable', 'image', 'mimes:jpeg,png,gif'],
            'registration_no' => ['required'],
            'symbol_no'      => ['required'],
        ]);
        if ($request->hasFile('image')) {
            if (!empty($student->image)) {
                Storage::delete('public/' . $student->image);
            }

            $data['image'] = MediaService::upload($request->file('image'), "students");
        } else {
            unset($data['image']);
        }

        $student->update($data);
        $student->fresh();
        return response()->json(['message' => 'Student has been Updated Successfully!', 'student' => $student], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Student  $student
     * @return \Illuminate\Http\Response
     */
    public function destroy(Student $student)
    {
        $this->authorize($student);
        $student->delete();

        return response()->json(['message' => 'Student has been deleted Successfully!'], 200);
    }

    public function documents(Student $student)
    {
        $documents = $student->documents()->get();

        return response()->json($documents);
    }

    public function storeDocument(Request $request, Student $student)
    {
        $request->validate([
            'type' => ['required'],
            'document' => ['required', 'file'],
        ]);

        $file = MediaService::upload($request->file('document'), 'documents');

        $document = Document::create([
            'student_id' => $student->id,
            'type' => $request->type,
            'file' => $file,
        ]);

        return response()->json(['message' => 'Document has been added Successfully!', 'documents' => $document], 200);
    }

    public function updateDocument(Request $request, Student $student, Document $document)
    {
        $request->validate([
            'type' => ['nullable'],
            'document' => ['nullable', 'file'],
        ]);

        /**
         * Due to PHP issue of not populating $_FILES variable when request is not
         * both multipart/form-data AND POST method, we cannot use Postman's or Axios's
         * PUT or PATCH method. Instead send request as POST but include "_method" = "PUT"
         * or "_METHOD" = "PATCH" when sending form request.
         *
         * This only applies when sending FILES via PUT or PATCH method. You can send text
         * input just fine.
         *
         * For more information see this: https://stackoverflow.com/a/65009135
         * or https://stackoverflow.com/a/65009227
         */
        if ($request->hasFile('document')) {
            if ($document->file) {
                Storage::delete('public/' . $document->file);
            }
            $file = MediaService::upload($request->file('document'), 'documents');
        } else {
            $file = $document->file;
        }

        $document->update([
            'type' => empty($request->type) ? $document->type : $request->type,
            'file' => $file,
        ]);
        $document->fresh();

        return response()->json(['message' => 'Document has been updated Successfully!', 'documents' => $document], 200);
    }

    public function showDocument(Student $student, Document $document)
    {
        return response()->json($document);
    }

    public function destroyDocument(Student $student, Document $document)
    {
        $document->delete();

        return response()->json(['message' => 'Document has been Deleted Succesully'], 200);
    }

    public function bookingRequest(Student $student)
    {
        $bookings = Borrow::whereNotNull('booking_at')->whereNull('issued_at')->get();

        return response()->json(['message' => 'Booking Request', 'booking' => $bookings]);
    }

    public function import()
    {
        Excel::import(new StudentsImport, request()->file('file'));
    }

    public function export()
    {
        return Excel::download(new StudentsExport, 'students.xlsx');
    }
}
