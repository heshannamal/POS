<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationRule extends Model
{
    protected $fillable = ['event_key','is_enabled','send_email','send_in_app','to_owner',
        'to_actor','to_platform','extra_emails'];

    protected function casts(): array
    {
        return [
            'is_enabled'=>'boolean','send_email'=>'boolean','send_in_app'=>'boolean',
            'to_owner'=>'boolean','to_actor'=>'boolean','to_platform'=>'boolean',
            'extra_emails'=>'array',
        ];
    }
}
