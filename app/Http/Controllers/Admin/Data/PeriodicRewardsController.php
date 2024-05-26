<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Currency\Currency;
use App\Models\Item\Item;
use App\Models\Loot\LootTable;
use App\Models\PeriodicReward;
use App\Models\Raffle\Raffle;
use App\Models\Submission\Submission;
use App\Models\User\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PeriodicRewardsController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Admin / Periodic Rewards Controller
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
    public function editPeriodicReward(Request $request, $model, $id)
    {
        $decodedmodel = urldecode(base64_decode($model));
        //check model + id combo exists
        $object = $decodedmodel::find($id);
        if (!$object) {
            throw new \Exception('Invalid object.');
        }

        $data = $request->only([
            'group_name', 'group_quantity', 'group_operator', 'group_rewardable_type', 'group_rewardable_id', 'group_rewardable_quantity',
        ]);

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

                    $operators = array("<", "=", ">","!=","<=",">=");
                    if (!$data['group_operator'][$key] || !in_array($data['group_operator'][$key], $operators)) {
                        throw new \Exception('One or more of the groups was not given a valid operator.');
                    }

                    $groups[] = (object) [
                        'group_name' => $data['group_name'][$key],
                        'group_operator' => $data['group_operator'][$key],
                        'group_quantity' => $data['group_quantity'][$key],
                    ];

                }
            } else {
                throw new \Exception('You cannot make a periodic reward with no reward groups...');
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
            } else {
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
                    'data' => json_encode(getDataReadyAssets($assets)),
                ]);
            }

            DB::commit();

            flash('Periodic rewards edited successfully.')->success();
            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollback();
            flash($e->getMessage())->error();
            return redirect()->back();
        }
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
     * Approves a submission.
     *
     * @param  array                  $data
     * @param  \App\Models\User\User  $user
     * @return mixed
     */
    public function grantPeriodicRewards($object, $user, $recipient, $logtype, $logdata, $logs)
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
                if ($reward->group_operator === '>' && $logs > $reward->group_quantity) {
                    $this->grantRewards($reward, $user, $recipient, $logtype, $logdata);
                } elseif ($reward->group_operator === '=' && $logs == $reward->group_quantity) {
                    $this->grantRewards($reward, $user, $recipient, $logtype, $logdata);
                } elseif ($reward->group_operator === '<' && $logs < $reward->group_quantity) {
                    $this->grantRewards($reward, $user, $recipient, $logtype, $logdata);
                }elseif ($reward->group_operator === '!=' && $logs != $reward->group_quantity) {
                    $this->grantRewards($reward, $user, $recipient, $logtype, $logdata);
                }elseif ($reward->group_operator === '<=' && $logs <= $reward->group_quantity) {
                    $this->grantRewards($reward, $user, $recipient, $logtype, $logdata);
                }elseif ($reward->group_operator === '>=' && $logs >= $reward->group_quantity) {
                    $this->grantRewards($reward, $user, $recipient, $logtype, $logdata);
                }

            }

            DB::commit();

            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollback();
            flash($e->getMessage())->error();
            return redirect()->back();
        }
    }

    /**
     * Processes reward data into a format that can be used for distribution.
     *
     * @param  array $data
     * @return array
     */
    private function grantRewards($reward, $user, $recipient, $logtype, $logdata)
    {
        // Distribute user rewards
        if (!$rewards = fillUserAssets(parseAssetData($reward->data), $user, $recipient, $logtype, $logdata)) {
            throw new \Exception("Failed to distribute rewards to user.");
        }
        flash('Periodic rewards granted successfully.')->success();
    }
}
