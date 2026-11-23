<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationDelivery extends Model
{
    protected $fillable = [
        'event_key','tenant_id','recipient_user_id','recipient_email','original_email',
        'subject','body_html','status','transport','attempts','last_error',
        'dedupe_key','queued_at','sent_at',
    ];

    protected $hidden = ['body_html'];

    protected function casts(): array
    {
        return ['body_html'=>'encrypted','queued_at'=>'datetime','sent_at'=>'datetime'];
    }
}
