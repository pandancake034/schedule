<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = ['date', 'shift_type', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}