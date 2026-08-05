<?php

namespace Hybridcore\Demo\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Pairs with the "demo.example" type registered via $registry->notificationTypes(). */
class DemoTestNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'demo.example',
            'message' => trans('demo::messages.notif_test'),
            'action_url' => route('admin.demo.index'),
            'action_label' => trans('demo::messages.nav'),
        ];
    }
}
