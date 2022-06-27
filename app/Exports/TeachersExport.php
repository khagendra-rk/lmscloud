<?php

namespace App\Exports;

use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\FromCollection;

class TeachersExport implements FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Teacher::select('id', 'name', 'email', 'college_email', 'phone_no', 'address')->get();
    }

    public function headings(): array
    {
        return ["ID", "Name", "Email", "College Email", "Phone No", "Address"];
    }
}
