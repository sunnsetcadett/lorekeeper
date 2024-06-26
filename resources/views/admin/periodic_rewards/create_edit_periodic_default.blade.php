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
    @if (!$default->id)
        <p> You can add the rewards themselves after the default is made.</p>
    @endif
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

    @if ($default->id)
        @include('widgets._periodic_loot_select', ['groups' => $default->periodicRewards, 'object' => $default, 'type' => 'submission', 'default' => true])
    @endif
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
