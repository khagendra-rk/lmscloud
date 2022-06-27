<?php

namespace App\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromCollection;

class StudentsExport implements FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Student::select('id', 'name', 'email', 'college_email', 'phone_no', 'address', 'faculty_id', 'parent_name', 'parent_contact', 'year', 'registration_no', 'symbol_no')->get();
    }

    public function headings(): array
    {
        return ["ID", "Name", "Email", "College Email", "Phone No", "Address", "Faculty ID", "Parent Name", "Parent Contact", "Year", "Registration No", "Symbol No"];
    }
}
