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
            $object->periodicRewards()->delete();

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
    public function grantPeriodicReward($object, $user, $recipient, $logtype, $logdata, $logs)
    {
        DB::beginTransaction();

        try {
            if (!$object) {
                throw new \Exception("Invalid object.");
            }

            if (!$recipient) {
                throw new \Exception("Invalid recipient.");
            }

            foreach ($object->periodicRewards as $reward) {
                //check log count
                if ($reward->group_operator !== 'every' && $this->checkCount($logs->count(), $reward) == true) {
                    $grant = $this->grantRewards($reward, $user, $recipient, $logtype, $logdata);
                } elseif ($reward->reward_timeframe !== 'lifetime' && !$this->checkLimitReached($reward, $logs) && $this->checkCount($logs->count(), $reward) == true) {
                    $grant = $this->grantRewards($reward, $user, $recipient, $logtype, $logdata);
                } elseif ($reward->group_operator === 'every' && $logs->count() % $reward->group_quantity === 0) {
                    $grant = $this->grantRewards($reward, $user, $recipient, $logtype, $logdata);
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
    public function grantRewards($reward, $user, $recipient, $logtype, $logdata)
    {
        DB::beginTransaction();

        try {
            // Distribute user rewards
            if (!$rewards = fillUserAssets(parseAssetData($reward->data), $user, $recipient, $logtype, $logdata)) {
                throw new \Exception("Failed to distribute rewards to user.");
            }
            flash('Periodic rewards granted successfully.')->success();

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
    public function makeLog($object, $user)
    {
        DB::beginTransaction();

        try {
            if (!$object) {
                throw new \Exception("Invalid object.");
            }

            if (!$user) {
                throw new \Exception("Invalid user.");
            }

            // make a log of the action.
            $Log = PeriodicRewardLog::create([
                'object_id' => $object->id,
                'object_type' => class_basename($object),
                'user_id' => $user->id,
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
            $default = PeriodicDefault::find($key);
            foreach ($default->periodicRewards as $default) {
                //duplicate that mf
                $duplicate = $default->replicate();
                $duplicate->object_type = class_basename($object);
                $duplicate->object_id = $object->id;

                $duplicate->save();
            }
        }
    }
}
