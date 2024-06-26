<h4>Bonus Rewards</h4>
@foreach ($object->periodicRewards as $group)
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
        <hr class="my-4 w-75" />
    @else
        <p>An error occurred. Contact an admin.</p>
    @endif
     @if (Auth::check())
            <div class="text-center">
                <strong>Your current {{ $act_type }} count for this reward:</strong> {{ $group->displayCount($group, $logs) }}
            </div>
        @endif
@endforeach
