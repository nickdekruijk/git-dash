<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareToken extends Model
{
    protected $fillable = ['token', 'connection', 'label'];
}
