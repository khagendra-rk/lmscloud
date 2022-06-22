<?php

namespace App\Http\Controllers\Borrow;

use App\Models\Index;
use App\Models\Borrow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class BorrowController extends Controller
{
    const BORROW_LIMIT = 5;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('viewAny', Borrow::class);
        $borrows = Borrow::with(['teacher', 'student', 'index.book'])->paginate(10);

        return response()->json($borrows);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create', Borrow::class);
        $data = $request->validate([
            'index_id' => ['required_if:code,null', 'exists:indices,id'],
            'code' => ['required_if:index_id,null'],
            'teacher_id' => ['required_without:student_id', 'exists:teachers,id'],
            'student_id' => ['required_without:teacher_id', 'exists:students,id'],
        ]);

        if (!empty($request->teacher_id) && !empty($request->student_id)) {
            throw ValidationException::withMessages([
                'teacher_id' => ['You need to either provide teacher ID or student ID.'],
                'student_id' => ['You need to either provide teacher ID or student ID.'],
            ]);
        }

        if ($request->index_id) {
            $index = Index::find($request->index_id);
        } else {
            $codes = explode("-", $request->code);
            if (count($codes) == 0) {
                throw ValidationException::withMessages([
                    'code' => ['Invalid code.'],
                ]);
            }

            $index = Index::where('book_prefix', $codes[0])->where('code', $codes[1])->first();
        }

        if (!$index && $request->code) {
            throw ValidationException::withMessages([
                'code' => ['Invalid code.'],
            ]);
        }

        if (!$index && $request->index_id) {
            throw ValidationException::withMessages([
                'index_id' => ['Invalid Index ID.'],
            ]);
        }

        if ($index->is_borrowed) {
            throw ValidationException::withMessages([
                'index_id' => ['Book has already been borrowed!'],
            ]);
        }

        $borrow = DB::transaction(function () use ($data, $index, $request) {
            $data = array_merge($data, [
                'issued_by' => auth()->user()->id,
                'issued_at' => now(),
            ]);

            $check = $this->checkBorrow($request->index_id, $request->teacher_id, $request->student_id);
            if ($check >= self::BORROW_LIMIT) {
                throw ValidationException::withMessages([
                    'teacher_id' => ['There is already ' . $check . ' book borrowed from this account. You cannot borrow more than ' . self::BORROW_LIMIT . ' book at a time!'],
                    'student_id' => ['There is already ' . $check . ' book borrowed from this account. You cannot borrow more than ' . self::BORROW_LIMIT . ' book at a time!'],
                ]);
            }
            $borrow = Borrow::create($data);
            $index->update(['is_borrowed' => true]);

            return $borrow;
        });

        return response()->json(['message' => 'Book has been issued successfully!', 'issues' => $borrow], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Borrow  $borrow
     * @return \Illuminate\Http\Response
     */
    public function show(Borrow $borrow)
    {
        $this->authorize('view', $borrow);
        $borrow->load(['student', 'teacher', 'index.book']);

        return response()->json($borrow);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Borrow  $borrow
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Borrow $borrow)
    {
        $this->authorize('update', $borrow);
        $data = $request->validate([
            'index_id' => ['nullable', 'exists:indices,id'],
            'teacher_id' => ['required_without:student_id', 'exists:teachers,id'],
            'student_id' => ['required_without:teacher_id', 'exists:students,id'],
        ]);

        if (!empty($request->teacher_id) && !empty($request->student_id)) {
            throw ValidationException::withMessages([
                'teacher_id' => ['You need to either provide teacher ID or student ID.'],
                'student_id' => ['You need to either provide teacher ID or student ID.'],
            ]);
        }

        if (empty($request->teacher_id)) {
            $data['teacher_id'] = null;
        } else {
            $data['student_id'] = null;
        }

        $remove_previous = false;
        $index_id = $request->index_id;
        if (empty($index_id)) {
            $index_id = $borrow->index_id;
        }

        $index = Index::find($index_id);

        if ($index_id != $borrow->index_id) {
            if ($index->is_borrowed) {
                throw ValidationException::withMessages([
                    'index_id' => ['Book has already been borrowed!'],
                ]);
            }

            $remove_previous = true;
        }

        DB::transaction(function () use ($data, $index, $remove_previous, $request, $borrow) {

            if ($remove_previous) {
                $borrow->index()->update(['is_borrowed' => false]);
            }

            if ($request->student_id != $borrow->student_id || $request->teacher_id != $borrow->teacher_id) {
                $check = $this->checkBorrow($request->index_id, $request->teacher_id, $request->student_id, $borrow->index_id);
                if ($check >= self::BORROW_LIMIT - 1) {
                    throw ValidationException::withMessages([
                        'teacher_id' => ['There is already ' . $check . ' book borrowed from this account. You cannot borrow more than ' . self::BORROW_LIMIT . ' book at a time!'],
                        'student_id' => ['There is already ' . $check . ' book borrowed from this account. You cannot borrow more than ' . self::BORROW_LIMIT . ' book at a time!'],
                    ]);
                }
            }

            $borrow->update(array_merge($data, [
                'issued_at' => now(),
            ]));

            $index->update(['is_borrowed' => true]);
        });

        $borrow->fresh();

        return response()->json(['message' => 'Borrow has been updated successfully!', 'returns' => $borrow], 200);
    }

    /**
     * Return the borrowed item.
     *
     * @param  \App\Models\Borrow  $borrow
     * @return \Illuminate\Http\Response
     */
    public function return(Borrow $borrow)
    {
        $this->authorize('return', $borrow);
        DB::transaction(function () use ($borrow) {
            $borrow->update(['returned_at' => now()]);
            $borrow->index()->update(['is_borrowed' => false]);
        });

        return response()->json(['message' => 'Book has been returned succesfully!'], 200);
    }

    /**
     * Return the borrowed item.
     *
     * @param  \App\Models\Borrow  $borrow
     * @return \Illuminate\Http\Response
     */
    public function returnIndex(Request $request)
    {
        $this->authorize('return', Borrow::class);

        $request->validate([
            'code' => ['required'],
            'prefix' => ['nullable'],
        ]);

        $code = $request->code;
        $prefix = $request->prefix;
        if (empty($request->prefix)) {
            $exp = explode("-", $code);
            if (count($exp) != 2) {
                throw ValidationException::withMessages([
                    'code' => ['Invalid Code!'],
                ]);
            }

            $prefix = $exp[0];
            $code = $exp[1];
        }


        $index = Index::where('book_prefix', $prefix)->where('code', $code)->first();
        if (!$index) {
            throw ValidationException::withMessages([
                'code' => ['Invalid Code!'],
            ]);
        }


        if (!$index->is_borrowed) {
            throw ValidationException::withMessages([
                'index_id' => ['Book has already been returned!'],
            ]);
        }

        $borrow = $index->borrows()->whereNull('returned_at')->first();
        if (!$borrow) {
            throw ValidationException::withMessages([
                'index_id' => ['Book has not been borrowed or has already been returned!'],
            ]);
        }

        DB::transaction(function () use ($borrow, $index) {
            $borrow->update(['returned_at' => now()]);
            $index->update(['is_borrowed' => false]);
        });

        return response()->json(['message' => 'Book has been return successfully!']);
    }

    public function checkBorrow($index, $teacher_id, $student_id, $index_id = null)
    {
        $this->authorize('checkBorrow', Borrow::class);

        $query = Borrow::whereNull('returned_at');

        if (!empty($teacher_id)) {
            $query = $query->where('teacher_id', $teacher_id);
        } else {
            $query = $query->where('student_id', $student_id);
        }

        if (!empty($index_id)) {
            $query = $query->where('index_id', '!=', $index_id);
        }

        return $query->count();
    }

    //  * Return the borrowed item.
    //  *
    //  * @param  \App\Models\Borrow  $borrow
    //  * @return \Illuminate\Http\Response
    //  */
    // public function returnIndexMultiple(Request $request)
    // {
    //     $request->validate([
    //         'indices' => ['required', 'array'],
    //         'indices.*' => ['required', 'exists:indices,id'],
    //     ]);

    //     DB::transaction(function () use ($request) {
    //         $request->validate([
    //             'code' => ['required'],
    //             'prefix' => ['nullable'],
    //         ]);

    //         $code = $request->code;
    //         $prefix = $request->prefix;
    //         if (empty($request->prefix)) {
    //             $exp = explode("-", $code);
    //             if (count($exp) != 2) {
    //                 throw ValidationException::withMessages([
    //                     'code' => ['Invalid Code!'],
    //                 ]);
    //             }

    //             $prefix = $exp[0];
    //             $code = $exp[1];
    //         }


    //         $index = Index::where('book_prefix', $prefix)->where('code', $code)->first();
    //         throw ValidationException::withMessages([
    //             'code' => ['Cannot find book from given code!'],
    //         ]);

    //         foreach ($request->indices as $ind) {
    //             $index = Index::find($ind);
    //             if (!$index->is_borrowed) {
    //                 throw ValidationException::withMessages([
    //                     'index_id' => ['Book has already been returned!'],
    //                 ]);
    //             }

    //             $borrow = $index->borrows()->where('returned_at', '')->first();
    //             if (!$borrow) {
    //                 throw ValidationException::withMessages([
    //                     'index_id' => ['Book has not been borrowed or has already been returned!'],
    //                 ]);
    //             }

    //             $borrow->update(['returned_at' => now()]);
    //             $index->update(['is_borrowed' => false]);
    //         }
    //     });


    //     return response()->noContent();
    // }
}
