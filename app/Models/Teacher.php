<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Teacher.
 *
 * @package App\Models
 */
class Teacher extends Model
{
    protected $guarded = [];

    /**
     * Get the user that owns the teacher.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}