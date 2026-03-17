<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedPopulation extends Model
{
    protected $table = 'feedPopulation';
    protected $primaryKey = 'idAssoc';
    public $timestamps = false;

    protected $fillable = [
        'idFeedIn',
        'idFeedOut',
        'enabled',
        'filterTypeUrl',
        'filterUrl',
        'filterTypeEmail',
        'filterEmail',
        'filterTypeListcode',
        'filterListcode',
        'forceUrlList',
        'forceUrl',
        'waterfallPriority',
        'queueType',
        'startDate',
        'populationType',
        'feedCategory',
    ];

    protected $casts = [
        'waterfallPriority' => 'integer',
    ];

    public function inboundFeed()
    {
        return $this->belongsTo(InboundFeed::class, 'idFeedIn', 'idFeedIn');
    }

    public function outboundFeed()
    {
        return $this->belongsTo(OutboundFeed::class, 'idFeedOut', 'idFeedOut');
    }
}
