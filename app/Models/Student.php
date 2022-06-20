<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone_no',
        'address',
        'email',
        'college_email',
        'parent_name',
        'parent_contact',
        'year',
        'registration_no',
        'symbol_no',
        'image',
        'faculty_id',
        'user_id',
    ];

    public function faculty()
    {
        return $this->belongsTo(Faculty::class);
    }

    public function borrows()
    {
        return $this->hasMany(Borrow::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
