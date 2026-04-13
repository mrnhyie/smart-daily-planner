<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 */
class Task extends Model
{
    // NO public $user_id here!
    protected $fillable = ['title', 'description', 'is_completed'];
    protected $casts = [
        'is_completed' => 'boolean',
    ];
//    protected $hidden = ['user_id'];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
