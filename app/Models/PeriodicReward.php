<?php

namespace App\Models;

use App\Models\Model;

class PeriodicReward extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'object_id', 'object_type', 'data','group_name','group_operator','group_quantity'
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'periodic_rewards';

    /**********************************************************************************************

    RELATIONS

     **********************************************************************************************/

    /**
     * Get the attachments.
     */
    public function object()
    {
        switch ($this->object_type) {
            case 'Prompt':
                return $this->belongsTo('App\Models\Prompt\Prompt', 'object_id');
        }
        return null;
    }

    /**********************************************************************************************

    ACCESSORS

     **********************************************************************************************/

    /**
     * Get the data attribute as an associative array.
     *
     * @return array
     */
    public function getDataAttribute()
    {
        return json_decode($this->attributes['data'], true);
    }

    /**
     * Get the rewards for the group.
     *
     * @return array
     */
    public function getRewardsAttribute()
    {
        $assets = parseAssetData($this->data);
        $rewards = [];
        foreach ($assets as $type => $a) {
            $class = getAssetModelString($type, false);
            foreach ($a as $id => $asset) {
                $rewards[] = (object) [
                    'rewardable_type' => $class,
                    'rewardable_id' => $id,
                    'quantity' => $asset['quantity'],
                ];
            }
        }
        return $rewards;
    }
}
