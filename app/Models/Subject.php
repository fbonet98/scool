<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Subject.
 *
 * @package App\Models
 */
class Subject extends Model
{
    protected $guarded = [];

    /**
     * Find by code.
     *
     * @param $code
     * @return mixed
     */
    public static function findByCode($code)
    {
        return self::where('code', $code)->first();
    }
}
