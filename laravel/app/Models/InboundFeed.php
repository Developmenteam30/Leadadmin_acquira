<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundFeed extends Model
{
    protected $table = 'feedinc';
    protected $primaryKey = 'idFeedIn';
    public $timestamps = false;

    protected $fillable = [
        'label',
        'description',
        'idCompany',
        'required',
        'allowedFields',
        'password',
        'dedupeEmail',
        'dedupeLandline',
        'dedupeCellphone',
        'rejectOldLeads',
        'rejectOldLeadsMaxAge',
        'dedupeAcross',
        'filterTypeUrl',
        'filterUrl',
        'filterTypeSiftLogic',
        'filterSiftLogic',
        'notifications',
        'status',
        'chokePercent',
        'feedCategory',
        'dailyLimit',
        'custom1Label',
        'custom2Label',
        'custom3Label',
        'custom4Label',
        'custom5Label',
        'custom6Label',
        'costPerLead',
        'revenuePerLeadType',
        'revenuePerLead',
        'notifyThresholdCount',
        'notifyThresholdDays',
        'notifyThresholdLastSent',
        'notifyThresholdTime',
        'salesperson',
        'paused',
        'pauseMessage',
        'filterTypeDNCScrub',
        'timezone',
        'timeskew',
        'filterState',
        'lookbackPeriod',
        'pingTimeout',
        'requiredPingFields',
        'allowedPingFields',
        'minimumBirthAge',
        'maximumBirthAge',
        'filterZip',
        'leadStatus',
    ];

    protected function casts(): array
    {
        return [
            'dedupeEmail' => 'boolean',
            'dedupeLandline' => 'boolean',
            'dedupeCellphone' => 'boolean',
            'rejectOldLeads' => 'boolean',
            'notifications' => 'boolean',
            'paused' => 'boolean',
            'chokePercent' => 'integer',
            'dailyLimit' => 'integer',
            'costPerLead' => 'decimal:4',
            'revenuePerLead' => 'decimal:2',
            'notifyThresholdCount' => 'integer',
            'lookbackPeriod' => 'integer',
            'pingTimeout' => 'integer',
            'minimumBirthAge' => 'integer',
            'maximumBirthAge' => 'integer',
            'filterState' => 'array',
            'filterZip' => 'array',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'idCompany', 'idCompany');
    }
}
