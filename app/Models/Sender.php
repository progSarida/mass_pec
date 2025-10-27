<?php

namespace App\Models;

use App\Enums\ConnectionSafetyType;
use App\Enums\MailProtocolType;
use App\Enums\MailType;
use App\Enums\ManagementType;
use Illuminate\Database\Eloquent\Model;

class Sender extends Model
{
    protected $fillable = [
        'cc',
        'management_type',
        'mail_type',
        'address',
        'username',
        'password',
        'public_name',
        'connection_safety_type',
        'in_mail_server',
        'in_mail_protocol_type',
        'in_mail_port',
        'out_mail_server',
        'out_mail_protocol_type',
        'out_mail_port',
        'out_authentication',
        'out_username',
        'out_password',
    ];

    protected $casts = [
        'management_type' => ManagementType::class,
        'mail_type' => MailType::class,
        'connection_safety_type' => ConnectionSafetyType::class,
        'in_mail_protocol_type' => MailProtocolType::class,
        'out_mail_protocol_type' => MailProtocolType::class,
        'out_authentication' => 'boolean',
    ];
}
