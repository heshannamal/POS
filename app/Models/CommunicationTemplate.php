<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationTemplate extends Model
{
    protected $fillable = ['event_key','name','subject','body_markdown','allowed_variables','is_active','updated_by'];

    protected function casts(): array
    {
        return ['allowed_variables' => 'array', 'is_active' => 'boolean'];
    }
}
