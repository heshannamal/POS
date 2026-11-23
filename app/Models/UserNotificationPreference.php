<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotificationPreference extends Model
{
    protected $fillable = ['user_id','event_key','email_enabled','in_app_enabled'];

    protected function casts(): array
    {
        return ['email_enabled'=>'boolean','in_app_enabled'=>'boolean'];
    }
}
