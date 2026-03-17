<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'companies';
    protected $primaryKey = 'idCompany';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'url',
        'note',
        'address',
        'city',
        'state',
        'zipcode',
        'country',
        'main_name',
        'main_phone',
        'main_email',
        'acct_name',
        'acct_phone',
        'acct_email',
        'tech_name',
        'tech_phone',
        'tech_email',
        'returns_name',
        'returns_phone',
        'returns_email',
        'accountManager',
        'accountOpener',
        'salesperson',
        'status',
        'isPublisher',
        'isAdvertiser',
        'isCallCenter',
        'paymentTerms',
        'costPerLead',
        'dialer_report_type',
    ];

    protected function casts(): array
    {
        return [
            'isPublisher' => 'boolean',
            'isAdvertiser' => 'boolean',
            'isCallCenter' => 'boolean',
            'costPerLead' => 'decimal:4',
            'country' => 'integer',
            'accountManager' => 'integer',
            'accountOpener' => 'integer',
            'salesperson' => 'integer',
        ];
    }

    public function divisions()
    {
        return $this->belongsToMany(Division::class, 'companies_divisions', 'companyId', 'divisionId');
    }

    public function verticals()
    {
        return $this->belongsToMany(Vertical::class, 'companies_verticals', 'companyId', 'verticalId');
    }
}
