<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PushSubscription;

/**
 * @property mixed $password
 */
class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'reminder_email',
        'reminder_sms',
        'reminder_push',
        'primary_channel',
        'preferences',
        'subscribed',
        'subscription_plan',
        'subscription_expires_at',
        'last_payment_reference',
        'reminder_attempts',
        'password',
    ];

    protected $casts = [
        'reminder_email' => 'boolean',
        'reminder_sms' => 'boolean',
        'reminder_push' => 'boolean',
        'preferences' => 'array',
        'subscribed' => 'boolean',
        'subscription_expires_at' => 'datetime',
        'reminder_attempts' => 'integer',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function checkSubscriptionExpiry(): bool
    {
        if ($this->subscribed && $this->subscription_expires_at && $this->subscription_expires_at->isPast()) {
            $this->update([
                'subscribed' => false,
                'subscription_plan' => null,
                'subscription_expires_at' => null,
            ]);
            \Illuminate\Support\Facades\Cache::forget("user_{$this->id}");
            return true;
        }
        return false;
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
}
