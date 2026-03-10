<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $recipients = DB::table('recipients')->get();

        foreach ($recipients as $recipient) {
            $order = 0;

            for ($i = 1; $i <= 5; $i++) {
                $mailField = "mail_$i";
                $typeField = "mail_type_$i";
                $officeField = "office_type_id_$i";

                if (!empty($recipient->$mailField)) {
                    DB::table('recipient_emails')->insert([
                        'recipient_id' => $recipient->id,
                        'email' => $recipient->$mailField,
                        'mail_type' => $recipient->$typeField,
                        'office_type_id' => $recipient->$officeField,
                        'order' => $order++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('recipient_emails')->truncate();
    }
};
