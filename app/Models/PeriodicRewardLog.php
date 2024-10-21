<?php

namespace App\Models;

use App\Models\Model;

class PeriodicRewardLog extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'object_id', 'object_type', 'user_id','user_type','log_key'
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'periodic_rewards_logs';

    /**
     * Whether the model contains timestamps to be saved and updated.
     *
     * @var string
     */
    public $timestamps = true;

    /**
     * Validation rules for creation.
     *
     * @var array
     */
    public static $createRules = [
        'object_id' => 'required',
        'object_type' => 'required',
        'user_id' => 'required',
    ];

    /**********************************************************************************************

    RELATIONS
     **********************************************************************************************/

    /**
     * Get the user
     */
    public function user()
    {
        if($this->user_type == 'User')return $this->belongsTo('App\Models\User\User', 'user_id');
        else return $this->belongsTo('App\Models\Character\Character', 'user_id');
    }

    /**
     * Get the object
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

    SCOPES
     **********************************************************************************************/

    /**
     * Scope a query to only include user's logs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLogCount($query, $object, $user, $logKey)
    {
        return $query->where([
            ['object_id', $object->id],
            ['user_id', $user->id],
            ['object_type', class_basename($object)],
            ['user_type', $user->logType],
            ['log_key', $logKey],
        ]);
    }

}
