@extends('layouts/edit-form', [
    'createText' => trans('admin/access-review/general.new_campaign'),
    'updateText' => trans('admin/access-review/general.edit_campaign'),
    'formAction' => (isset($item->id))
        ? route('access-review.campaigns.update', ['campaign' => $item->id])
        : route('access-review.campaigns.store'),
])

@section('inputFields')

    <div class="form-group {{ $errors->has('name') ? ' has-error' : '' }}">
        <label for="name" class="col-md-3 control-label">{{ trans('admin/access-review/general.name') }}</label>
        <div class="col-md-8 col-sm-12">
            <input class="form-control" style="width:100%;" type="text" name="name" aria-label="name" id="name" value="{{ old('name', $item->name) }}" required maxlength="191" />
            {!! $errors->first('name', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        </div>
    </div>

    <div class="form-group {{ $errors->has('description') ? ' has-error' : '' }}">
        <label for="description" class="col-md-3 control-label">{{ trans('admin/access-review/general.description') }}</label>
        <div class="col-md-8 col-sm-12">
            <textarea class="form-control" style="width:100%;" name="description" aria-label="description" id="description" rows="3">{{ old('description', $item->description) }}</textarea>
            {!! $errors->first('description', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        </div>
    </div>

    {{-- Opt-in: unchecked means launching this campaign mails nobody. See the
         guard in CampaignsController::launch(). Reminders are unaffected. --}}
    <div class="form-group {{ $errors->has('notify_managers_on_launch') ? ' has-error' : '' }}">
        <label for="notify_managers_on_launch" class="col-md-3 control-label">{{ trans('admin/access-review/general.notify_managers_on_launch') }}</label>
        <div class="col-md-8 col-sm-12">
            <label class="form-control">
                {{-- Paired hidden field so an unchecked box still submits a value. Without
                     it, old() has no key on a validation redisplay and falls back to the
                     stored value, silently re-ticking a box the user had just cleared. --}}
                <input type="hidden" name="notify_managers_on_launch" value="0">
                <input type="checkbox" name="notify_managers_on_launch" value="1" id="notify_managers_on_launch" aria-label="notify_managers_on_launch" @checked(old('notify_managers_on_launch', $item->notify_managers_on_launch))>
                {{ trans('general.yes') }}
            </label>
            <p class="help-block">{{ trans('admin/access-review/general.notify_managers_on_launch_help') }}</p>
            {!! $errors->first('notify_managers_on_launch', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
        </div>
    </div>

    @include('partials.forms.edit.company-select', [
        'translated_name' => trans('admin/access-review/general.companies'),
        'fieldname'       => 'company_ids',
        'multiple'        => 'true',
        'selected'        => old('company_ids', $item->company_ids ?? []),
    ])

@stop
