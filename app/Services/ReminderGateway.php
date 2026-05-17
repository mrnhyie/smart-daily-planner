<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ReminderGateway
{
    public function sendSms(string $to, string $message): void
    {
        $response = Http::timeout(15)->post($this->baseUrl().'/send-sms', [
            'to' => $to,
            'message' => $message,
        ]);

        $response->throw();
    }

    public function sendWhatsapp(string $to, string $message): void
    {
        $response = Http::timeout(15)->post($this->baseUrl().'/send-whatsapp', [
            'to' => $to,
            'message' => $message,
        ]);

        $response->throw();
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('services.reminders.base_url'), '/');
    }
}
