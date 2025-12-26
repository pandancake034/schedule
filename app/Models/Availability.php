<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Availability extends Model
{
    protected $fillable = ['user_id', 'day_of_week', 'shift_preference'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}