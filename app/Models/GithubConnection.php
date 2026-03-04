<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GithubConnection extends Model
{
    protected $fillable = ['name', 'label', 'token'];

    protected $casts = [
        'token' => 'encrypted',
    ];
}
