<?php

namespace App\Domain\Support\Services;

use Illuminate\Support\Facades\DB;

class TicketService
{
    /** Opens a support/part-finder ticket with an SLA deadline and initial customer message. */
    public function open(?int $userId, string $subject, string $message, string $department = 'support', string $priority = 'normal'): int
    {
        return DB::transaction(function () use ($userId, $subject, $message, $department, $priority) {
            $number = 'TKT-'.now()->format('ymd').'-'.random_int(100000, 999999);
            $id = DB::table('tickets')->insertGetId(['number' => $number, 'user_id' => $userId, 'department' => $department, 'priority' => $priority, 'status' => 'open', 'subject' => $subject, 'first_response_due_at' => now()->addHours($priority === 'urgent' ? 2 : 8), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('ticket_messages')->insert(['ticket_id' => $id, 'user_id' => $userId, 'body' => $message, 'is_internal' => false, 'created_at' => now()]);

            return $id;
        });
    }

    /** Appends a public or internal reply while preserving the complete ticket timeline. */
    public function reply(int $ticketId, ?int $userId, string $body, bool $internal = false): void
    {
        DB::table('ticket_messages')->insert(['ticket_id' => $ticketId, 'user_id' => $userId, 'body' => $body, 'is_internal' => $internal, 'created_at' => now()]);
        DB::table('tickets')->where('id', $ticketId)->update(['updated_at' => now()]);
    }
}
