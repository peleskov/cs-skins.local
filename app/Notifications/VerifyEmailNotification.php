<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Подтверждение Email адреса - ' . config('app.name'))
            ->greeting('Здравствуйте, ' . $notifiable->name . '!')
            ->line('Пожалуйста, подтвердите ваш email адрес, нажав на кнопку ниже.')
            ->action('Подтвердить Email', $verificationUrl)
            ->line('Ссылка действительна в течение 24 часов.')
            ->line('Если вы не регистрировались на нашем сайте, просто проигнорируйте это письмо.')
            ->salutation('С уважением, команда ' . config('app.name'));
    }

    /**
     * Получить URL для верификации email
     */
    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'profile.verify.email',
            now()->addHours(24),
            [
                'id' => $notifiable->id,
                'hash' => sha1($notifiable->email),
            ]
        );
    }
}
