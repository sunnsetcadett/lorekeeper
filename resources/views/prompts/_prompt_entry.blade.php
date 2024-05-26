<div class="row world-entry">
    @if ($prompt->has_image)
        <div class="col-md-3 world-entry-image"><a href="{{ $prompt->imageUrl }}" data-lightbox="entry" data-title="{{ $prompt->name }}"><img src="{{ $prompt->imageUrl }}" class="world-entry-image" alt="{{ $prompt->name }}" /></a></div>
    @endif
    <div class="{{ $prompt->has_image ? 'col-md-9' : 'col-12' }}">
        <div class="mb-3">
            <h3 class="mb-0">{!! $prompt->name !!}</h3>
            @if ($prompt->prompt_category_id)
                <div><strong>Category: </strong>{!! $prompt->category->displayName !!}</div>
            @endif
            @if ($prompt->start_at && $prompt->start_at->isFuture())
                <div><strong>Starts: </strong>{!! format_date($prompt->start_at) !!} ({{ $prompt->start_at->diffForHumans() }})</div>
            @endif
            @if ($prompt->end_at)
                <div><strong>Ends: </strong>{!! format_date($prompt->end_at) !!} ({{ $prompt->end_at->diffForHumans() }})</div>
            @endif
        </div>
        <div class="world-entry-text">
            <p>{{ $prompt->summary }}</p>
            <div class="text-right"><a data-toggle="collapse" href="#prompt-{{ $prompt->id }}" class="text-primary"><strong>Show details...</strong></a></div>
            <div class="collapse" id="prompt-{{ $prompt->id }}">
                <h4>Details</h4>
                @if ($prompt->parsed_description)
                    {!! $prompt->parsed_description !!}
                @else
                    <p>No further details.</p>
                @endif
                @if ($prompt->hide_submissions == 1 && isset($prompt->end_at) && $prompt->end_at > Carbon\Carbon::now())
                    <p class="text-info">Submissions to this prompt are hidden until this prompt ends.</p>
                @elseif($prompt->hide_submissions == 2)
                    <p class="text-info">Submissions to this prompt are hidden.</p>
                @endif
            </div>
            <h4>Rewards</h4>
            @if (!count($prompt->rewards))
                No rewards.
            @else
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th width="70%">Reward</th>
                            <th width="30%">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($prompt->rewards as $reward)
                            <tr>
                                <td>{!! $reward->reward->displayName !!}</td>
                                <td>{{ $reward->quantity }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            @if (count($prompt->periodicRewards))
                <h4>Bonus Rewards</h4>
                @foreach ($prompt->periodicRewards as $group)
                    @if (array_filter(parseAssetData($group->data)))
                        <p>Only gives reward if your submission count is <strong>{{ $group->group_operator == '=' ? 'equal to' : ($group->group_operator == '>' ? 'greater than' : ($group->group_operator == '<' ? 'less than' : ($group->group_operator == '!=' ? 'not equal to' : ($group->group_operator == '<=' ? 'less than OR equal to' : ($group->group_operator == '>=' ? 'greater than OR equal to' : []))))) }}</strong>
                            {{ $group->group_quantity }}.</p>
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
                @endforeach
                @if (Auth::check())
                    <div class="text-center">
                        <strong>Your current count:</strong> {{ \App\Models\Submission\Submission::submitted($prompt->id, Auth::user()->id)->count() }}
                    </div>
                @endif
            @endif
        </div>
        <div class="text-right">
            @if ($prompt->end_at && $prompt->end_at->isPast())
                <span class="text-secondary">This prompt has ended.</span>
            @elseif($prompt->start_at && $prompt->start_at->isFuture())
                <span class="text-secondary">This prompt is not open for submissions yet.</span>
            @else
                <a href="{{ url('submissions/new?prompt_id=' . $prompt->id) }}" class="btn btn-primary">Submit Prompt</a>
            @endunless
    </div>
</div>
</div>
