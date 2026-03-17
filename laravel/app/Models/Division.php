<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $table = 'divisions';
    protected $primaryKey = 'divisionId';
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function verticals()
    {
        return $this->hasMany(Vertical::class, 'divisionId', 'divisionId');
    }
}
