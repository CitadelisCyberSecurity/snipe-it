@component('mail::message')
{{ trans('admin/access-review/general.email_launched_greeting', ['name' => $notifiable->first_name]) }}

{{ trans('admin/access-review/general.email_launched_body', [
    'campaign' => $campaign->name,
    'count'    => $itemCount,
]) }}

@component('mail::button', ['url' => url(route('access-review.my-reviews.show', $campaign))])
{{ trans('admin/access-review/general.email_launched_cta') }}
@endcomponent

{{ trans('mail.best_regards') }}
@if ($snipeSettings->show_url_in_emails == '1')
<p><a href="{{ config('app.url') }}">{{ $snipeSettings->site_name }}</a></p>
@else
<p>{{ $snipeSettings->site_name }}</p>
@endif
@endcomponent
