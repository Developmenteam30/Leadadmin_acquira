<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vertical extends Model
{
    protected $table = 'verticals';
    protected $primaryKey = 'verticalId';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'divisionId',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class, 'divisionId', 'divisionId');
    }
}
