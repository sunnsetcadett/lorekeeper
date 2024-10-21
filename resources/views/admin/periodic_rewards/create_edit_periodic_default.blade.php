@extends('admin.layout')

@section('admin-title')
    Default Periodic Rewards
@endsection

@section('admin-content')
    {!! breadcrumbs([
        'Admin Panel' => 'admin',
        'Default Periodic Rewards' => 'admin/data/periodic-defaults',
        ($default->id ? 'Edit' : 'Create') . ' Default' => $default->id ? 'admin/data/periodic-defaults/edit/' . $default->id : 'admin/data/periodic-defaults/create',
    ]) !!}

    <h1>{{ $default->id ? 'Edit' : 'Create' }} Default Periodic Reward
        @if ($default->id)
            <a href="#" class="btn btn-danger float-right delete-default-button">Delete Default Periodic Reward</a>
        @endif
    </h1>

    {!! Form::open(['url' => $default->id ? 'admin/data/periodic-defaults/edit/' . $default->id : 'admin/data/periodic-defaults/create', 'files' => true]) !!}

    <h3>Basic Information</h3>

    <div class="form-group">
        {!! Form::label('Name') !!}
        {!! Form::text('name', $default->name, ['class' => 'form-control']) !!}
    </div>


    <div class="form-group">
        {!! Form::label('Summary (Optional)') !!}
        {!! Form::text('summary', $default->summary, ['class' => 'form-control']) !!}
    </div>

    <div class="text-right">
        {!! Form::submit($default->id ? 'Edit' : 'Create', ['class' => 'btn btn-primary']) !!}
    </div>

    {!! Form::close() !!}

    <div class="alert alert-info"><p>You can add both user and character rewards to populate in. You can't convert character rewards to user rewards and vice versa, even when toggling on this default in the user or character reward editor, so make your decisions carefully!</div>

    @include('widgets._periodic_loot_select', [
        'object' => $default,
        'action' => 'action',
        'objectname' => 'default',
        'recipient' => 'User',
        'reward_key' => 'periodicRewards',
        'default' => true,
    ])

    @include('widgets._periodic_loot_select', [
        'object' => $default,
        'action' => 'action',
        'objectname' => 'default',
        'recipient' => 'Character',
        'showHr' => true,
        'reward_key' => 'periodicCharacterRewards',
        'default' => true,
    ])

@endsection

@section('scripts')
    @parent
    <script>
        $(document).ready(function() {
            $('.delete-default-button').on('click', function(e) {
                e.preventDefault();
                loadModal("{{ url('admin/data/periodic-defaults/delete') }}/{{ $default->id }}", 'Delete Default');
            });
        });
    </script>
@endsection
