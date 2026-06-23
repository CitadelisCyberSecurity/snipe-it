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

    @include('partials.forms.edit.company-select', [
        'translated_name' => trans('admin/access-review/general.companies'),
        'fieldname'       => 'company_ids',
        'multiple'        => 'true',
        'selected'        => old('company_ids', $item->company_ids ?? []),
    ])

@stop
