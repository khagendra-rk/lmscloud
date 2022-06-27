<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;

class TeachersImport implements ToModel
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
                'name' => $row['name'],
                'email' => $row['college_email'],
                'password' => Hash::make($row['password']),
            ]);
            $user->teacher()->create([
                'name' => $row['name'],
                'phone_no' => $row['phone_no'],
                'address' => $row['address'],
                'email' => $row['email'],
                'college_email' => $row['college_email'],
                'password' => $row['password'],
                'user_id' => $user->id,
            ]);
        });
    }
}
