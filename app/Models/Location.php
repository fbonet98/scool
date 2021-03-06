<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Location.
 *
 * @package Acacha\Relationships\Models
 */
class Location extends Model
{
    protected $guarded = [];

    /**
     * Find by name.
     *
     * @param $name
     * @return mixed
     */
    public static function findByName($name)
    {
        return self::where('name',$name)->first();
    }
}
