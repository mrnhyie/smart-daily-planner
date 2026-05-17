<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PushSubscription;

/**
 * @property mixed $password
 */
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'reminder_whatsapp',
        'reminder_sms',
        'reminder_push',
        'password',
    ];

    protected $casts = [
        'reminder_whatsapp' => 'boolean',
        'reminder_sms' => 'boolean',
        'reminder_push' => 'boolean',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
}
