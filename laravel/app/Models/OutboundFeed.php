<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboundFeed extends Model
{
    protected $table = 'feedout';
    protected $primaryKey = 'idFeedOut';
    public $timestamps = false;

    protected $fillable = [
        'label',
        'description',
        'idCompany',
        'feedType',
        'postUrl',
        'staticFields',
        'varFields',
        'fieldMap',
        'cron',
        'cronTiming',
        'successString',
        'throttle',
        'urlassignments',
        'dailyLimit',
        'delay',
        'queued',
        'status',
        'feedCategory',
        'delayDump',
        'notifyThresholdCount',
        'notifyThresholdTime',
        'notifyThresholdLastSent',
        'notifyThresholdDays',
        'revenuePerLead',
        'launchDate',
        'costPerLeadOverride',
        'costKey',
        'valueMap',
        'salesperson',
        'xmlDTD',
        'processingSchedule',
        'staticFieldsJSON',
        'varFieldsJSON',
        'timezone',
        'leadStatus',
    ];

    protected function casts(): array
    {
        return [
            'idCompany' => 'integer',
            'cronTiming' => 'integer',
            'throttle' => 'integer',
            'dailyLimit' => 'integer',
            'delay' => 'integer',
            'queued' => 'integer',
            'delayDump' => 'boolean',
            'notifyThresholdCount' => 'integer',
            'revenuePerLead' => 'decimal:4',
            'costPerLeadOverride' => 'decimal:4',
            'salesperson' => 'integer',
            'cron' => 'boolean',
            'staticFieldsJSON' => 'array',
            'varFieldsJSON' => 'array',
            'launchDate' => 'date',
            'notifyThresholdLastSent' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'idCompany', 'idCompany');
    }
}
