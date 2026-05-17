<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 */
class Task extends Model
{
    // NO public $user_id here!
    protected $fillable = [
        'title',
        'description',
        'is_completed',
        'reminder_time',
        'task_order',
        'last_whatsapp_reminded_on',
        'last_sms_reminded_on',
        'last_push_reminded_on',
    ];
    protected $casts = [
        'is_completed' => 'boolean',
        'task_order' => 'integer',
        'last_whatsapp_reminded_on' => 'date:Y-m-d',
        'last_sms_reminded_on' => 'date:Y-m-d',
        'last_push_reminded_on' => 'date:Y-m-d',
    ];
//    protected $hidden = ['user_id'];

    protected function reminderTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? substr($value, 0, 5) : null,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
