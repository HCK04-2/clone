<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Rdv;

class AppointmentCancelled extends Notification
{
    use Queueable;

    protected $rdv;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Rdv $rdv)
    {
        $this->rdv = $rdv;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'title' => 'Rendez-vous annulé',
            'message' => 'Un rendez-vous a été annulé.',
            'date' => now(),
            'appointment_id' => $this->rdv->id,
            'patient_id' => $this->rdv->patient_id,
            'doctor_id' => $this->rdv->target_user_id,
        ];
    }
}
