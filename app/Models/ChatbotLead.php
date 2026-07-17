<?php

namespace App\Models;

use App\Enums\ChatbotLeadStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatbotLead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'company_name',
        'product',
        'message',
        'status',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChatbotLeadStatus::class,
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
