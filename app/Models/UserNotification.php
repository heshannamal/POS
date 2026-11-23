<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = [
        'recipient_user_id','tenant_id','event_key','title','message',
        'action_url','dedupe_key','read_at',
    ];

    protected function casts(): array
    {
        return ['read_at'=>'datetime'];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class,'recipient_user_id');
    }
}
