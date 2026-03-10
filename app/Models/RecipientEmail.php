<?php

namespace App\Models;

use App\Enums\MailType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipientEmail extends Model
{
    protected $fillable = [
        'recipient_id',
        'email',
        'mail_type',
        'office_type_id',
        'order',
    ];

    protected $casts = [
        'mail_type' => MailType::class,
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    public function officeType(): BelongsTo
    {
        return $this->belongsTo(OfficeType::class);
    }
}
