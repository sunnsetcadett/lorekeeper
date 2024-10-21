@php
    //idk what to name this variable, it's in case you want to add an addition to the title ($keytitle user rewards) given that now we have reward keys for multiple attachments to the same model

    //so instead of just periodic rewards you could add some other textual identifier for another purpose so you won't confuse yourself with multiple identical fields.
    if (!isset($keytitle)) {
        $keytitle = '';
    }
@endphp

@if (!$object->id)
    <hr style="margin-top: 3em;">
    <div class="card mb-2">
        <div class="card-header h2">
            {{ $keytitle }} Periodic Rewards ({!! $recipient == 'User' ? 'User <i class="fas fa-user"></i>' : 'Character <i class="fas fa-paw"></i>' !!} )
        </div>
        <div class="card-body" style="clear:both;">
            <p>You can create <strong>{{ $keytitle }} {{ $recipient }} periodic rewards</strong> once the {{ $objectname }} has been made.</p>
            </p>
        </div>
    </div>
@else
    {!! Form::open(['url' => 'admin/data/periodic-rewards/edit/' . base64_encode(urlencode(get_class($object))) . '/' . $object->id]) !!}


    @php
        // This file represents a common source and definition for assets used in loot_select
        // While it is not per se as tidy as defining these in the controller(s),
        // doing so this way enables better compatibility across disparate extensions

        //isUser variable for character specifics
        $isUser = $recipient == 'User';

        if ($isUser) {
            $items = \App\Models\Item\Item::orderBy('name')->pluck('name', 'id');
            $currencies = \App\Models\Currency\Currency::where('is_user_owned', 1)->orderBy('name')->pluck('name', 'id');
            $raffles = \App\Models\Raffle\Raffle::where('rolled_at', null)->where('is_active', 1)->orderBy('name')->pluck('name', 'id');
        } else {
            $items = \App\Models\Item\Item::whereIn('item_category_id', \App\Models\Item\ItemCategory::where('is_character_owned', 1)->pluck('id')->toArray())
                ->orderBy('name')
                ->pluck('name', 'id');
            $currencies = \App\Models\Currency\Currency::where('is_character_owned', 1)->orderBy('sort_character', 'DESC')->pluck('name', 'id');
        }
        $tables = \App\Models\Loot\LootTable::orderBy('name')->pluck('name', 'id');
    @endphp

    {!! Form::hidden('recipient_type', $recipient) !!}
    {!! Form::hidden('reward_key', $reward_key) !!}
    <hr style="margin-top: 3em;">

    <div class="card mb-2">
        @if (!isset($default))
            <div class="card-header h2">
                Populate Default {{ $keytitle }} Periodic Rewards ({!! $isUser ? 'User <i class="fas fa-user"></i>' : 'Character <i class="fas fa-paw"></i>' !!} )
            </div>
            <div class="card-body" style="clear:both;">
                <p>You can populate this {{ $objectname }} with the selected defaults.
                <div class="text-muted">Toggle the desired reward sets to "on" to populate their rewards.</div>
                </p>
                @php
                    $defaults = \App\Models\PeriodicDefault::orderBy('name')->get();
                @endphp
                <div class="row">
                    @foreach ($defaults as $default)
                        <div class="col-md form-group">
                            {!! Form::checkbox('default_periodic_rewards[' . $default->id . ']', 1, 0, ['class' => 'form-check-input', 'data-toggle' => 'toggle']) !!}
                            {!! Form::label('default_periodic_rewards[' . $default->id . ']', $default->name, ['class' => 'form-check-label ml-3']) !!} {!! add_help($default->summary) !!}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        <div class="card-header">
            <a href="#" class="btn btn-outline-info float-right" id="addGroup{{ $reward_key }}{{ $recipient }}">Add Group</a>
            <div data-toggle="collapse" href="#collapseperiodic{{ $reward_key }}{{ $recipient }}">
                <h2>{{ $keytitle }} Periodic Rewards ({!! $isUser ? 'User <i class="fas fa-user"></i>' : 'Character <i class="fas fa-paw"></i>' !!} )</h2>
                <div class="text-muted">Display is collapsed to shorten the page, click to expand</div>
            </div>
        </div>
        <div class="card-body collapse collapsed" style="clear:both;" id="collapseperiodic{{ $reward_key }}{{ $recipient }}">
            <p>Create a group (or multiple) to assign rewards to. You can set a number of {{ $action }}s, as well as a math type (greater, equal, or less than) the {{ $isUser ? 'user' : 'character' }}'s {{ $action }} total.</p>
            <p>These rewards will be distributed according to the checks that you set.</p>
            @if (isset($info))
                <div class="alert alert-info">{!! $info !!}</div>
            @endif
            <div id="groups{{ $reward_key }}{{ $recipient }}" class="mb-2">
                @if ($object->$reward_key)
                    @foreach ($object->$reward_key as $group)
                        <div class="submission-group mb-2 card">
                            <div class="card-body">
                                <div class="text-right"><a href="#" class="remove-group{{ $reward_key }}{{ $recipient }} text-muted"><i class="fas fa-times"></i></a></div>
                                <div class="row">
                                    <div class="col-md-10">
                                        <div class="form-group">
                                            {!! Form::label('group_name[]', 'Group Name') !!}{!! add_help('For reference purposes admin-side. Also used as the group key.') !!}
                                            {!! Form::text('group_name[]', $group->group_name, ['class' => 'form-control group-name']) !!}
                                        </div>
                                        <p class="text-center">Set a number of {{ $action }}s, as well as math operator. As long as the {{ $isUser ? 'user' : 'character' }}'s {{ $action }}s are logged (if adding this somewhere else),
                                            then rewards will be distributed
                                            according to these specifications.</p>
                                        <p class="text-center">For example: If you set quantity to 1 and the operator as =, the {{ $isUser ? 'user' : 'character' }} will only get the chosen rewards if this is their first {{ $action }}.</p>
                                        <p class="text-center">When adding a timeframe, {{ $action }}s will <strong>only</strong> be counted during that timeframe. Yearly will only look at {{ $action }}s made after the beginning of the
                                            current year. Weekly starts on Sunday. Rollover will happen on UTC time.</p>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    {!! Form::label('group_quantity[]', ucfirst($action) . ' Quantity') !!}
                                                    {!! Form::number('group_quantity[]', $group->group_quantity, [
                                                        'class' => 'form-control mr-2 group-rewardable-min',
                                                    ]) !!}
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    {!! Form::label('group_operator[]', 'Reward Operator') !!}
                                                    {!! Form::select(
                                                        'group_operator[]',
                                                        [
                                                            '>' => '> (Greater Than Quantity)',
                                                            '=' => '= (Equal To Quantity)',
                                                            '<' => '< (Less Than Quantity)',
                                                            '!=' => '!= (Not Equal To Quantity)',
                                                            '<=' => '<= (Less Than OR Equal To Quantity)',
                                                            '>=' => '>= (Greater Than OR Equal To Quantity)',
                                                            'every' => 'Every (Quantity)',
                                                        ],
                                                        $group->group_operator,
                                                        [
                                                            'class' => 'form-control mr-2 group-rewardable-min',
                                                        ],
                                                    ) !!}
                                                </div>
                                            </div>
                                            <div class="col-md-6 form-group">
                                                {!! Form::label('reward_timeframe[]', 'Reward Limit Timeframe') !!}
                                                {!! Form::select('reward_timeframe[]', ['lifetime' => 'Lifetime', 'yearly' => 'Yearly', 'monthly' => 'Monthly', 'weekly' => 'Weekly', 'daily' => 'Daily'], $group->reward_timeframe, [
                                                    'class' => 'form-control',
                                                ]) !!}
                                            </div>
                                        </div>
                                        <div class="group-rewards">
                                            <h4>Group Rewards</h4>
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th width="35%">Reward Type</th>
                                                        <th width="35%">Reward</th>
                                                        <th width="30%">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="group-rewards">
                                                    @foreach ($group->rewards ?? [] as $reward)
                                                        <tr class="group-reward-row{{ $reward_key }}{{ $recipient }}">

                                                            <td>
                                                                {!! Form::select('group_rewardable_type[' . $group->group_name . '][]', ['Item' => 'Item', 'Currency' => 'Currency', 'LootTable' => 'Loot Table'] + ($isUser ? ['Raffle' => 'Raffle Ticket'] : []), $reward->rewardable_type, [
                                                                    'class' => 'form-control group-rewardable-type',
                                                                    'placeholder' => 'Select Reward Type',
                                                                ]) !!}
                                                            </td>
                                                            <td class="lootDivs{{ $reward_key }}{{ $recipient }}">
                                                                <div class="group-currencies  {{ $reward->rewardable_type == 'Currency' ? 'show' : 'hide' }}">{!! Form::select('group_rewardable_id[' . $group->group_name . '][]', $currencies, $reward->rewardable_type == 'Currency' ? $reward->rewardable_id : null, [
                                                                    'class' => 'form-control group-currency-id selectize',
                                                                    'placeholder' => 'Select Currency',
                                                                ]) !!}</div>
                                                                <div class="group-items  {{ $reward->rewardable_type == 'Item' ? 'show' : 'hide' }}">{!! Form::select('group_rewardable_id[' . $group->group_name . '][]', $items, $reward->rewardable_type == 'Item' ? $reward->rewardable_id : null, ['class' => 'form-control group-item-id selectize', 'placeholder' => 'Select Item']) !!}</div>
                                                                <div class="group-tables {{ $reward->rewardable_type == 'LootTable' ? 'show' : 'hide' }}">{!! Form::select('group_rewardable_id[' . $group->group_name . '][]', $tables, $reward->rewardable_type == 'LootTable' ? $reward->rewardable_id : null, [
                                                                    'class' => 'form-control group-table-id selectize',
                                                                    'placeholder' => 'Select Loot Table',
                                                                ]) !!}</div>
                                                                @if ($isUser)
                                                                    <div class="group-raffles {{ $reward->rewardable_type == 'Raffle' ? 'show' : 'hide' }}">{!! Form::select('group_rewardable_id[' . $group->group_name . '][]', $raffles, $reward->rewardable_type == 'Raffle' ? $reward->rewardable_id : null, [
                                                                        'class' => 'form-control group-raffle-id selectize',
                                                                        'placeholder' => 'Select Raffle',
                                                                    ]) !!}</div>
                                                                @endif
                                                            </td>
                                                            <td class="d-flex align-items-center">
                                                                {!! Form::number('group_rewardable_quantity[' . $group->group_name . '][]', $reward->quantity, ['class' => 'form-control mr-2 group-rewardable-quantity']) !!}
                                                                <a href="#" class="remove-reward{{ $reward_key }}{{ $recipient }} d-block"><i class="fas fa-times text-muted"></i></a>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            <div class="text-right">
                                                <a href="#" class="btn btn-outline-primary btn-sm add-reward{{ $reward_key }}{{ $recipient }}">Add
                                                    Reward</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <div class="text-right">
        {!! Form::submit('Edit ' . $keytitle . ' ' . $recipient . ' Rewards', ['class' => 'btn btn-primary']) !!}
    </div>

    {!! Form::close() !!}

    @if (isset($showHr) && $showHr)
        <hr style="margin-bottom: 1em;">
    @endif

    <div id="groupComponents{{ $reward_key }}{{ $recipient }}" class="hide">
        <div class="submission-group mb-2 card">
            <div class="card-body">
                <div class="text-right"><a href="#" class="remove-group{{ $reward_key }}{{ $recipient }} text-muted"><i class="fas fa-times"></i></a>
                </div>
                <div class="row">
                    <div class="col-md-10">
                        <div class="form-group group-info">
                            {!! Form::label('group_name[]', 'Group Name') !!}{!! add_help('For reference purposes admin-side.') !!}
                            {!! Form::text('group_name[]', null, ['class' => 'form-control group-name']) !!}
                        </div>
                        <p class="text-center">Set a number of {{ $action }}s, as well as math operator. As long as the {{ $isUser ? 'user' : 'character' }}'s {{ $action }}s are logged (if adding this somewhere else), then rewards will
                            be distributed according to
                            these
                            specifications.</p>
                        <p class="text-center">For example: If you set quantity to 1 and the operator as =, the {{ $isUser ? 'user' : 'character' }} will only get the chosen rewards if this is their first {{ $action }}.</p>
                        <p class="text-center">When adding a timeframe, {{ $action }}s will <strong>only</strong> be counted during that timeframe. Yearly will only look at {{ $action }}s made after the beginning of the current year.
                            Weekly
                            starts on Sunday. Rollover will happen on UTC time.</p>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    {!! Form::label('group_quantity[]', ucfirst($action) . ' Quantity') !!}
                                    {!! Form::number('group_quantity[]', 1, [
                                        'class' => 'form-control mr-2 group-rewardable-min',
                                    ]) !!}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    {!! Form::label('group_operator[]', 'Reward Operator') !!}
                                    {!! Form::select(
                                        'group_operator[]',
                                        [
                                            '>' => '> (Greater Than Quantity)',
                                            '=' => '= (Equal To Quantity)',
                                            '<' => '< (Less Than Quantity)',
                                            '!=' => '!= (Not Equal To Quantity)',
                                            '<=' => '<= (Less Than OR Equal To Quantity)',
                                            '>=' => '>= (Greater Than OR Equal To Quantity)',
                                            'every' => 'Every (Quantity)',
                                        ],
                                        null,
                                        [
                                            'class' => 'form-control mr-2 group-rewardable-min',
                                        ],
                                    ) !!}
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                {!! Form::label('reward_timeframe[]', 'Reward Limit Timeframe') !!}
                                {!! Form::select('reward_timeframe[]', ['lifetime' => 'Lifetime', 'yearly' => 'Yearly', 'monthly' => 'Monthly', 'weekly' => 'Weekly', 'daily' => 'Daily'], null, [
                                    'class' => 'form-control',
                                ]) !!}
                            </div>
                        </div>
                        <div class="col-md-10">
                            <a href="#" class="float-right fas fa-close"></a>
                            <div class="group-rewards">
                                <h4>Rewards</h4>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th width="35%">Reward Type</th>
                                            <th width="35%">Reward</th>
                                            <th width="30%">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="group-rewards">
                                    </tbody>
                                </table>
                                <div class="text-right">
                                    <a href="#" class="btn btn-outline-primary btn-sm add-reward{{ $reward_key }}{{ $recipient }}">Add Reward</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <table>
            <tr class="group-reward-row{{ $reward_key }}{{ $recipient }}">
                <td>
                    {!! Form::select('group_rewardable_type[]', ['Item' => 'Item', 'Currency' => 'Currency', 'LootTable' => 'Loot Table'] + ($isUser ? ['Raffle' => 'Raffle Ticket'] : []), null, [
                        'class' => 'form-control group-rewardable-type',
                        'placeholder' => 'Select Reward Type',
                    ]) !!}
                </td>
                <td class="lootDivs{{ $reward_key }}{{ $recipient }}">
                    <div class="group-currencies hide">{!! Form::select('group_currency_id[]', $currencies, 0, ['class' => 'form-control group-currency-id selectize', 'placeholder' => 'Select Currency']) !!}</div>
                    <div class="group-items hide">{!! Form::select('group_item_id[]', $items, 0, ['class' => 'form-control group-item-id selectize', 'placeholder' => 'Select Item']) !!}</div>
                    <div class="group-tables hide">{!! Form::select('group_rewardable_id[]', $tables, 0, ['class' => 'form-control group-table-id selectize', 'placeholder' => 'Select Loot Table']) !!}</div>
                    @if ($isUser)
                        <div class="group-raffles hide">{!! Form::select('group_rewardable_id[]', $raffles, 0, ['class' => 'form-control group-raffle-id selectize', 'placeholder' => 'Select Raffle']) !!}</div>
                    @endif
                </td>

                <td class="d-flex align-items-center">
                    {!! Form::number('group_rewardable_quantity[]', 1, ['class' => 'form-control mr-2 group-rewardable-quantity']) !!}
                    <a href="#" class="remove-reward{{ $reward_key }}{{ $recipient }} d-block"><i class="fas fa-times text-muted"></i></a>
                </td>
            </tr>
        </table>
    </div>

    <script>
        $(document).ready(function() {
            var $addGroup = $('#addGroup{{ $reward_key }}{{ $recipient }}');
            var $components = $('#groupComponents{{ $reward_key }}{{ $recipient }}');
            var $rewards = $('#rewards{{ $reward_key }}{{ $recipient }}');
            var $groups = $('#groups{{ $reward_key }}{{ $recipient }}');
            var count = 0;

            $('#groups{{ $reward_key }}{{ $recipient }} .submission-group').each(function(index) {
                attachListeners($(this));
            });

            $('#groups{{ $reward_key }}{{ $recipient }} .selectize').selectize();

            $addGroup.on('click', function(e) {
                e.preventDefault();
                $clone = $components.find('.submission-group').clone();
                attachListeners($clone);
                attachRewardTypeListener($clone.find('.group-rewardable-type'));
                $groups.append($clone);
                count++;
            });

            function attachListeners(node) {
                node.find('.group-name').on('change', function(e) {
                    updateRewardNames(node, node.find('.group-name').val());
                });
                node.find('.remove-group{{ $reward_key }}{{ $recipient }}').on('click', function(e) {
                    e.preventDefault();
                    $(this).parent().parent().parent().remove();
                });
                node.find('.remove-reward{{ $reward_key }}{{ $recipient }}').on('click', function(e) {
                    e.preventDefault();
                    $(this).parent().parent().remove();
                });
                node.find('.add-reward{{ $reward_key }}{{ $recipient }}').on('click', function(e) {
                    e.preventDefault();
                    $clone = $components.find('.group-reward-row{{ $reward_key }}{{ $recipient }}').clone();
                    $clone.find('.remove-reward{{ $reward_key }}{{ $recipient }}').on('click', function(e) {
                        e.preventDefault();
                        $(this).parent().parent().remove();
                    });
                    updateRewardNames($clone, node.find('.group-name').val());
                    $(this).parent().parent().find('.group-rewards').append($clone);
                    $clone.find('.selectize').selectize();
                    attachRewardTypeListener(node.find('.group-rewardable-type'));
                });
                attachRewardTypeListener(node.find('.group-rewardable-type'));
            }

            function attachRewardTypeListener(node) {
                node.on('change', function(e) {
                    var val = $(this).val();
                    var $cell = $(this).parent().parent().find('.lootDivs{{ $reward_key }}{{ $recipient }}');

                    $cell.children().addClass('hide');
                    $cell.children().children().val(null);

                    if (val == 'Item') {
                        $cell.children('.group-items').addClass('show');
                        $cell.children('.group-items').removeClass('hide');
                        $cell.children('.group-items');
                    } else if (val == 'Currency') {
                        $cell.children('.group-currencies').addClass('show');
                        $cell.children('.group-currencies').removeClass('hide');
                    } else if (val == 'LootTable') {
                        $cell.children('.group-tables').addClass('show');
                        $cell.children('.group-tables').removeClass('hide');
                    } else if (val == 'Raffle') {
                        $cell.children('.group-raffles').addClass('show');
                        $cell.children('.group-raffles').removeClass('hide');
                    }
                });
            }

            function updateRewardNames(node, $input) {
                node.find('.group-rewardable-type').attr('name', 'group_rewardable_type[' + $input + '][]');
                node.find('.group-rewardable-quantity').attr('name', 'group_rewardable_quantity[' + $input + '][]');
                node.find('.group-currency-id').attr('name', 'group_rewardable_id[' + $input + '][]');
                node.find('.group-item-id').attr('name', 'group_rewardable_id[' + $input + '][]');
                node.find('.group-table-id').attr('name', 'group_rewardable_id[' + $input + '][]');
                node.find('.group-raffle-id').attr('name', 'group_rewardable_id[' + $input + '][]');
            }

        });
    </script>
@endif
