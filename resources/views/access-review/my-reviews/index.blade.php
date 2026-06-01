@extends('layouts/default')

@section('title')
    {{ trans('admin/access-review/general.my_reviews') }}
    @parent
@stop

@section('content')
    <x-container>
        <x-box>
            @if($campaigns->isEmpty())
                <p class="text-muted">{{ trans('admin/access-review/general.no_active_reviews') }}</p>
            @else
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ trans('admin/access-review/general.campaign') }}</th>
                            <th>{{ trans('admin/access-review/general.status') }}</th>
                            <th>{{ trans('admin/access-review/general.progress') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($campaigns as $campaign)
                            @php
                                $isComplete = $campaign->my_completed_count == $campaign->my_items_count;
                                $pct = $campaign->my_items_count > 0
                                    ? round($campaign->my_reviewed_count / $campaign->my_items_count * 100)
                                    : 0;
                            @endphp
                            <tr>
                                <td>{{ $campaign->name }}</td>
                                <td>
                                    @if($isComplete)
                                        <span class="label label-success">
                                            {{ trans('admin/access-review/general.review_status_complete') }}
                                        </span>
                                    @else
                                        <span class="label label-warning">
                                            {{ trans('admin/access-review/general.review_status_in_progress') }}
                                        </span>
                                    @endif
                                </td>
                                <td style="min-width:180px;">
                                    <div class="progress" style="margin-bottom:4px; background-color:#ddd;">
                                        <div class="progress-bar progress-bar-{{ $isComplete ? 'success' : 'info' }}"
                                             style="width:{{ $pct }}%"></div>
                                    </div>
                                    <small>
                                        {{ trans('admin/access-review/general.progress', [
                                            'reviewed' => $campaign->my_reviewed_count,
                                            'total'    => $campaign->my_items_count,
                                        ]) }}
                                    </small>
                                </td>
                                <td>
                                    @if(! $isComplete)
                                        <a href="{{ route('access-review.my-reviews.show', $campaign) }}"
                                           class="btn btn-sm btn-primary">
                                            {{ trans('admin/access-review/general.review') }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-box>
    </x-container>
@stop
