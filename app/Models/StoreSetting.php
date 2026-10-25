<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = [
        'mart_name',
        'address',
        'contact_number',
        'logo_path',
    ];
}
