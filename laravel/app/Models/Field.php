<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Field extends Model
{
    protected $table = 'fields';
    protected $primaryKey = 'fieldId';
    public $timestamps = false;

    protected $fillable = [
        'fieldName',
        'fieldType',
        'fieldDescription',
        'fieldFormat',
        'fieldDefinition',
    ];
}
