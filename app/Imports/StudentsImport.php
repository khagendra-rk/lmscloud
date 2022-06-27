<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;

class StudentsImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        DB::transaction(function () use ($row) {
            $user = User::create([
                'name'     => $row['name'],
                'email'    => $row['college_email'],
                'password' => Hash::make($row['password']),
            ]);

            $user->student()->create([
                'name'         => $row['name'],
                'phone_no'     => $row['phone_no'],
                'address'      => $row['address'],
                'email'        => $row['email'],
                'college_email' => $row['college_email'],
                'password' => $row['password'],
                'faculty_id'   => $row['faculty_id'],
                'parent_name'  => $row['parent_name'],
                'parent_contact' => $row['parent_contact'],
                'year'         => $row['year'],
                'registration_no' => $row['registration_no'],
                'symbol_no'    => $row['symbol_no'],
                'user_id'      => $user->id,
            ]);
        });
    }
}
