<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Services\PeriodicRewardsManager;
use Illuminate\Http\Request;
use App\Models\PeriodicDefault;
use App\Services\Service;

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
    public function editPeriodicReward(Request $request, PeriodicRewardsManager $service, $model, $id)
    {
        $decodedmodel = urldecode(base64_decode($model));
        //check model + id combo exists
        $object = $decodedmodel::find($id);
        if (!$object) {
            throw new \Exception('Invalid object.');
        }

        $data = $request->only([
            'group_name', 'group_quantity', 'group_operator', 'group_rewardable_type', 'group_rewardable_id', 'group_rewardable_quantity', 'reward_timeframe','default_periodic_rewards','recipient_type','reward_key'
        ]);

        if ($service->createPeriodicReward($object, $data)) {
            flash('Periodic rewards edited successfully.')->success();
        } else {
            foreach ($service->errors()->getMessages()['error'] as $error) {
                flash($error)->error();
            }

        }
        return redirect()->back();
    }

    /**
     * Shows the default period rewards controller
     */
    public function getDefaultIndex()
    {
        return view('admin.periodic_rewards.periodic_defaults', [
            'defaults' => PeriodicDefault::get(),
        ]);
    }

    /**
     * Shows the create / edit page for a default
     */
    public function getCreateEditPeriodicDefault($id = null)
    {
        return view('admin.periodic_rewards.create_edit_periodic_default', [
            'default' => $id ? PeriodicDefault::where('id', $id)->first() : new PeriodicDefault,
        ]);
    }

    /**
     * Creates a default
     */
    public function postCreateEditPeriodicDefault(Request $request, PeriodicRewardsManager $service, $id = null)
    {

        $data = $request->only(['name', 'summary']);

        if ($id && $service->updatePeriodicDefault(PeriodicDefault::find($id), $data)) {
            flash('Periodic reward default updated successfully.')->success();
        } else if (!$id && $default = $service->createPeriodicDefault($data)) {
            flash('Periodic reward default created successfully.')->success();
            return redirect()->to('admin/data/periodic-defaults/edit/' . $default->id);
        } else {
            foreach ($service->errors()->getMessages()['error'] as $error) {
                flash($error)->error();
            }

        }
        return redirect()->back();
    }

    /**
     * Gets the default deletion modal.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getDeletePeriodicDefault($id)
    {
        $default = PeriodicDefault::find($id);
        return view('admin.periodic_rewards._delete_periodic_default', [
            'default' => $default,
        ]);
    }

    /**
     * Deletes a default.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  App\Services\ItemService  $service
     * @param  int                       $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postDeletePeriodicDefault(Request $request, PeriodicRewardsManager $service, $id)
    {
        if ($id && $service->deletePeriodicDefault(PeriodicDefault::find($id))) {
            flash('Periodic reward default deleted successfully.')->success();
        } else {
            foreach ($service->errors()->getMessages()['error'] as $error) {
                flash($error)->error();
            }

        }
        return redirect()->to('admin/data/periodic-defaults');
    }

}
