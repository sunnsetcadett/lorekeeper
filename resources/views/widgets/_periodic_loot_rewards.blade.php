<hr class="w-75" />
<div class="card mb-3">
    <div class="card-header">
        <h4>Bonus Rewards ({!! $recipient == 'User' ? 'User <i class="fas fa-user"></i>' : 'Character <i class="fas fa-paw"></i>' !!} )</h4>
    </div>
    <div class="card-body inventory-body">
        @if ($object->$reward_key->count())
            @if (isset($info))
                <div class="alert alert-info">{!! $info !!}</div>
            @endif
            <ul class="nav nav-tabs">
                @foreach ($object->$reward_key as $group)
                    <li class="nav-item">
                        <a class="nav-link {{ $loop->first ? 'active' : '' }}" data-toggle="tab" href="#tab{{ $object->id }}{{ $loop->index + 1 }}{{ $group->reward_key }}{{ $recipient }}" role="tab">
                            Set {{ $loop->index + 1 }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="card-body tab-content image-info-box">
                @foreach ($object->$reward_key as $group)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab{{ $object->id }}{{ $loop->index + 1 }}{{ $group->reward_key }}{{ $recipient }}">
                        @if (array_filter(parseAssetData($group->data)))
                            <p>Only gives reward {!! $group->rewardDisplay($act_type) !!}.
                            </p>
                            <p>
                                @if ($group->reward_timeframe !== 'lifetime')
                                    {{ ucfirst($act_type) }}s only count
                                    <strong>{{ $group->reward_timeframe }}</strong>.
                                @endif
                            </p>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th width="70%">Reward</th>
                                        <th width="30%">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (parseAssetData($group->data) as $key => $type)
                                        @if (count($type))
                                            <tr>
                                                <td colspan="2"><strong>{!! strtoupper($key) !!}</strong></td>
                                            </tr>
                                            @foreach ($type as $asset)
                                                <tr>
                                                    <td>{!! $asset['asset']->displayName !!}</td>
                                                    <td>{{ $asset['quantity'] }}</td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                            <hr class="w-75" />
                        @else
                            <p>An error occurred. Contact an admin.</p>
                        @endif
                        @if (Auth::check() && isset($logs))
                            <div class="text-center">
                                <strong>Your current {{ $act_type }} count for this reward:</strong> {{ $group->displayCount($group, $logs) }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p>No rewards.</p>
        @endif
    </div>
</div>
@if (isset($showHr) && $showHr)
    <hr class=" w-75" />
@endif
