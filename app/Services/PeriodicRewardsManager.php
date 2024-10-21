<?php namespace App\Services;

use App\Models\Currency\Currency;
use App\Models\Item\Item;
use App\Models\Loot\LootTable;
use App\Models\PeriodicDefault;
use App\Models\PeriodicReward;
use App\Models\PeriodicRewardLog;
use App\Models\Raffle\Raffle;
use App\Services\Service;
use DB;

class PeriodicRewardsManager extends Service
{
    /*
    |--------------------------------------------------------------------------
    | Periodic Rewards Manager
    |--------------------------------------------------------------------------
    |
    | Handles creation/editing of periodic rewards
    |
     */

    /**
     * Edit periodic rewards
     *
     * @param  \Illuminate\Http\Request    $request
     * @param  int|null                    $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createPeriodicReward($object, $data)
    {
        DB::beginTransaction();

        try {
            if(!isset($data['reward_key'])){
                throw new \Exception('You must set a reward key.');
            }
            if(!isset($data['recipient_type'])){
                throw new \Exception('You must select a recipient.');
            }

            $groups = [];
            if (isset($data['group_name'])) {
                foreach ($data['group_name'] as $key => $id) {
                    if (!$data['group_name'][$key]) {
                        throw new \Exception('One or more of the groups was not given an internal name.');
                    }
                    if (!$data['group_quantity'][$key] || $data['group_quantity'][$key] < 1) {
                        throw new \Exception('One or more of the groups was not given a quantity or was set to 0 or less.');
                    }

                    $operators = array("<", "=", ">", "!=", "<=", ">=", "every");
                    if (!$data['group_operator'][$key] || !in_array($data['group_operator'][$key], $operators)) {
                        throw new \Exception('One or more of the groups was not given a valid operator.');
                    }

                    if (isset($data['reward_timeframe'][$key]) && !in_array($data['reward_timeframe'][$key], array("lifetime", "yearly", "monthly", "weekly", "daily"))) {
                        throw new \Exception('One or more of the groups was not given a valid timeframe.');
                    }

                    $groups[] = (object) [
                        'group_name' => $data['group_name'][$key],
                        'group_operator' => $data['group_operator'][$key],
                        'group_quantity' => $data['group_quantity'][$key],
                        'reward_timeframe' => isset($data['reward_timeframe'][$key]) ? $data['reward_timeframe'][$key] : null,
                    ];
                }
            }
            // Retrieve all reward IDs for groups
            $currencyIds = [];
            $itemIds = [];
            $tableIds = [];
            $raffleIds = [];
            if (isset($data['group_rewardable_id'])) {
                $data['group_rewardable_id'] = array_map(array($this, 'innerNull'), $data['group_rewardable_id']);
                foreach ($data['group_rewardable_id'] as $ckey => $c) {
                    foreach ($c as $key => $id) {

                        switch ($data['group_rewardable_type'][$ckey][$key]) {
                            case 'Currency':$currencyIds[] = $id;
                                break;
                            case 'Item':$itemIds[] = $id;
                                break;
                            case 'LootTable':$tableIds[] = $id;
                                break;
                            case 'Raffle':$raffleIds[] = $id;
                                break;
                        }
                    }
                }
            } elseif (isset($data['group_name'])) {
                throw new \Exception('Cannot make a group with no rewards.');
            }

            array_unique($currencyIds);
            array_unique($itemIds);
            array_unique($tableIds);
            array_unique($raffleIds);
            $currencies = Currency::whereIn('id', $currencyIds)->where('is_user_owned', 1)->get()->keyBy('id');
            $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');
            $tables = LootTable::whereIn('id', $tableIds)->get()->keyBy('id');
            $raffles = Raffle::whereIn('id', $raffleIds)->where('rolled_at', null)->where('is_active', 1)->get()->keyBy('id');

            // We're going to remove all groups and reattach them with the updated data

            //update the key variable because for some reason it doesn't like being called directly?????????????
            $rewardkey = $data['reward_key'];
            $object->$rewardkey()->delete();

            // Attach groups
            foreach ($groups as $c) {
                // Users might not pass in clean arrays (may contain redundant data) so we need to clean that up
                $assets = $this->processRewards($data + ['currencies' => $currencies, 'items' => $items, 'tables' => $tables, 'raffles' => $raffles], $c->group_name);

                // Now we have a clean set of assets (redundant data is gone, duplicate entries are merged)
                // so we can attach the group to the submission
                PeriodicReward::create([
                    'object_id' => $object->id,
                    'object_type' => class_basename($object),
                    'group_quantity' => $c->group_quantity,
                    'group_operator' => $c->group_operator,
                    'group_name' => $c->group_name,
                    'reward_timeframe' => $c->reward_timeframe,
                    'data' => json_encode(getDataReadyAssets($assets)),
                    'recipient_type' => $data['recipient_type'],
                    'reward_key' => $data['reward_key'],
                ]);
            }

            //check if we're porting over the defaults
            if (isset($data['default_periodic_rewards'])) {
                $this->populateDefaults($data, $object);
            }

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }
        return $this->rollbackReturn(false);
    }

    private function innerNull($value)
    {
        return array_values(array_filter($value));
    }

    /**
     * Processes reward data into a format that can be used for distribution.
     *
     * @param  array $data
     * @return array
     */
    private function processRewards($data, $name)
    {
        $assets = createAssetsArray();

        if (isset($data['group_rewardable_type'][$name]) && isset($data['group_rewardable_id'][$name]) && isset($data['group_rewardable_quantity'][$name])) {

            $data['group_rewardable_id'] = array_map(array($this, 'innerNull'), $data['group_rewardable_id']);

            foreach ($data['group_rewardable_id'][$name] as $key => $reward) {
                switch ($data['group_rewardable_type'][$name][$key]) {
                    case 'Currency':if ($data['group_rewardable_quantity'][$name][$key]) {
                            addAsset($assets, $data['currencies'][$reward], $data['group_rewardable_quantity'][$name][$key]);
                        }

                        break;
                    case 'Item':if ($data['group_rewardable_quantity'][$name][$key]) {
                            addAsset($assets, $data['items'][$reward], $data['group_rewardable_quantity'][$name][$key]);
                        }

                        break;
                    case 'LootTable':if ($data['group_rewardable_quantity'][$name][$key]) {
                            addAsset($assets, $data['tables'][$reward], $data['group_rewardable_quantity'][$name][$key]);
                        }

                        break;
                    case 'Raffle':if ($data['group_rewardable_quantity'][$name][$key]) {
                            addAsset($assets, $data['raffles'][$reward], $data['group_rewardable_quantity'][$name][$key]);
                        }

                        break;
                }
            }
        }
        return $assets;
    }

/**
 * Edit periodic rewards
 *
 * @param  \Illuminate\Http\Request    $request
 * @param  int|null                    $id
 * @return \Illuminate\Http\RedirectResponse
 */
    public function grantPeriodicReward($object, $user, $recipient, $logtype, $logdata, $logs, $rewardKey, $isCharacter = false)
    {
        DB::beginTransaction();

        try {
            if (!$object) {
                throw new \Exception("Invalid object.");
            }

            if (!$recipient) {
                throw new \Exception("Invalid recipient.");
            }

            foreach ($object->$rewardKey as $reward) {
                //check log count
                if ($reward->group_operator !== 'every' && $this->checkCount($logs->count(), $reward) == true) {
                    $grant = $this->grantRewards($reward, $user, $recipient, $logtype, $logdata, $isCharacter);
                } elseif ($reward->reward_timeframe !== 'lifetime' && !$this->checkLimitReached($reward, $logs) && $this->checkCount($logs->count(), $reward) == true) {
                    $grant = $this->grantRewards($reward, $user, $recipient, $logtype, $logdata, $isCharacter);
                } elseif ($reward->group_operator === 'every' && $logs->count() % $reward->group_quantity === 0) {
                    $grant = $this->grantRewards($reward, $user, $recipient, $logtype, $logdata, $isCharacter);
                }

            }
            if (isset($grant)) {
                return $this->commitReturn($grant);
            }
            return $this->commitReturn(true);

        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }
        return $this->rollbackReturn(false);
    }

    /**
     * Edit periodic rewards
     *
     * @param  \Illuminate\Http\Request    $request
     * @param  int|null                    $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function grantRewards($reward, $user, $recipient, $logtype, $logdata, $isCharacter = false)
    {
        DB::beginTransaction();

        try {
            if ($isCharacter) {
                // Distribute character rewards
                if (!($rewards = fillCharacterAssets(parseAssetData($reward->data), null, $recipient, $logtype, $logdata, $user))) {
                    throw new \Exception('Failed to distribute rewards to character.');
                }
            } else {
                // Distribute user rewards
                if (!$rewards = fillUserAssets(parseAssetData($reward->data), $user, $recipient, $logtype, $logdata)) {
                    throw new \Exception("Failed to distribute rewards to user.");
                }
            }

            flash(($isCharacter ? 'Character' : 'User') . ' periodic rewards granted successfully.')->success();

            return $this->commitReturn($rewards);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }
        return $this->rollbackReturn(false);
    }

    /**
     * Edit periodic rewards
     *
     * @param  \Illuminate\Http\Request    $request
     * @param  int|null                    $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function makeLog($object, $recipient, $logKey)
    {
        DB::beginTransaction();

        try {
            if (!$object) {
                throw new \Exception("Invalid object.");
            }

            if (!$recipient) {
                throw new \Exception("Invalid recipient.");
            }

            // make a log of the action.
            $Log = PeriodicRewardLog::create([
                'object_id' => $object->id,
                'object_type' => class_basename($object),
                'user_id' => $recipient->id,
                'log_key' => $logKey,
                'user_type' => $recipient->logType,
            ]);

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }
        return $this->rollbackReturn(false);
    }

    public function checkCount($logs, $reward)
    {
        if ($reward->group_operator === '>' && $logs > $reward->group_quantity || $reward->group_operator === '=' && $logs == $reward->group_quantity || $reward->group_operator === '<' && $logs < $reward->group_quantity || $reward->group_operator === '!=' && $logs != $reward->group_quantity || $reward->group_operator === '<=' && $logs <= $reward->group_quantity || $reward->group_operator === '>=' && $logs >= $reward->group_quantity) {
            return true;
        }
    }

    public function checkLimitReached($reward, $logs)
    {
        return $this->checkLogDates($reward, $logs)->count() == $reward->group_quantity;
    }

    public function checkLogDates($reward, $logs)
    {
        $date = $reward->rewardLimitDate;
        $logs = isset($date) ? $logs->where('created_at', '>=', date("Y-m-d H:i:s", $date)) : $logs;

        return $logs;
    }

    /**
     * Create a default.
     */
    public function createPeriodicDefault($data)
    {
        DB::beginTransaction();

        try {
            $default = PeriodicDefault::create($data);

            return $this->commitReturn($default);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }
        return $this->rollbackReturn(false);
    }

    /**
     * Update a default.
     */
    public function updatePeriodicDefault($default, $data)
    {
        DB::beginTransaction();

        try {

            $default->update($data);
            $default->save();

            return $this->commitReturn($default);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }
        return $this->rollbackReturn(false);
    }

    /**
     * delete a default.
     */
    public function deletePeriodicDefault($default)
    {
        DB::beginTransaction();

        try {
            $default->periodicRewards()->delete();
            $default->periodicCharacterRewards()->delete();

            $default->delete();

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }
        return $this->rollbackReturn(false);
    }

    /**
     * populate defaults
     */
    public function populateDefaults($data, $object)
    {
        foreach (array_filter($data['default_periodic_rewards']) as $key => $toggle) {
            $pdefault = PeriodicDefault::find($key);

            //i'm not going to account for custom reward keys here but you can do similar to populate them in
            if($pdefault->periodicRewards()->count()){
                foreach ($pdefault->periodicRewards as $default) {
                    //duplicate that mf
                    $duplicate = $default->replicate();
                    $duplicate->object_type = class_basename($object);
                    $duplicate->object_id = $object->id;

                    $duplicate->save();
                }
            }
            if($pdefault->periodicCharacterRewards()->count()){
                foreach ($pdefault->periodicCharacterRewards as $default) {
                    //duplicate that mf
                    $duplicate = $default->replicate();
                    $duplicate->object_type = class_basename($object);
                    $duplicate->object_id = $object->id;

                    $duplicate->save();
                }
            }
        }
    }
}
