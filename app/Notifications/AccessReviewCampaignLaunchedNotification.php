<?php

namespace App\Notifications;

use App\Models\AccessReviewCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class AccessReviewCampaignLaunchedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public AccessReviewCampaign $campaign,
        public int $itemCount,
    ) {}

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(trans('admin/access-review/general.email_launched_subject', [
                'campaign' => $this->campaign->name,
            ]))
            ->markdown('notifications.access-review.CampaignLaunched', [
                'campaign'  => $this->campaign,
                'itemCount' => $this->itemCount,
                'notifiable' => $notifiable,
            ])
            ->withSymfonyMessage(function (Email $message) {
                $message->getHeaders()->addTextHeader('X-System-Sender', 'Snipe-IT');
            });
    }
}
