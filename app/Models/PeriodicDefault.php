<?php

namespace App\Models;

use App\Models\Model;
use App\Services\PeriodicRewardsManager;

class PeriodicDefault extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'summary'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'periodic_defaults';

    /**********************************************************************************************

    RELATIONS

     **********************************************************************************************/

    /**
     * Get the rewards attached to this prompt.
     */
    public function periodicRewards()
    {
        return $this->hasMany('App\Models\PeriodicReward', 'object_id')->where([
            ['object_type', class_basename($this)],
            ['recipient_type', 'User'],
            ['reward_key', 'periodicRewards'],
        ]);
    }

    public function periodicCharacterRewards()
    {
        return $this->hasMany('App\Models\PeriodicReward', 'object_id')->where([
            ['object_type', class_basename($this)],
            ['recipient_type', 'Character'],
            ['reward_key', 'periodicCharacterRewards'],
        ]);
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

    public function getRewardLimitDateAttribute()
    {
        switch ($this->reward_timeframe) {
            case 'yearly':
                $date = strtotime('January 1st');
                break;
            case 'monthly':
                $date = strtotime('midnight first day of this month');
                break;
            case 'weekly':
                $date = strtotime('last sunday');
                break;
            case 'daily':
                $date = strtotime('midnight');
                break;
            default:
                $date = null;
        }

        return $date;
    }

    public function getRewardOperatorDisplayAttribute()
    {
        switch ($this->group_operator) {
            case '=':
                $operator = 'equal to';
                break;
            case '>':
                $operator = 'greater than';
                break;
            case '<':
                $operator = 'less than';
                break;
            case '!=':
                $operator = 'not equal to';
                break;
            case '<=':
                $operator = 'less than OR equal to';
                break;
            case '>=':
                $operator = 'greater than OR equal to';
                break;
            case 'every':
                $operator = 'every';
                break;
        }

        return $operator;
    }

    public function rewardDisplay($act_type)
    {
        if ($this->group_operator === 'every') {
            return '<strong>every</strong>
        ' . $this->group_quantity . ' ' . $act_type . 's';
        }
        return 'if your ' . $act_type . ' count is
        <strong>' . $this->rewardOperatorDisplay . '</strong>
        ' . $this->group_quantity;
    }

    public function displayCount($reward, $logs)
    {
        if ($this->reward_timeframe !== 'lifetime') {
            return (new PeriodicRewardsManager)->checkLogDates($reward, $logs);
        } else {
            return $logs->count();
        }
    }
}
