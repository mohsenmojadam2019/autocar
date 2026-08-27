<?php

namespace App\Domain\Content\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContentRevisionService
{
    /** Captures a complete JSON snapshot before a mutable content entity is changed. */
    public function snapshot(Model $model, ?string $note = null): int
    {
        return (int) DB::table('content_revisions')->insertGetId([
            'revisable_type' => $model::class,
            'revisable_id' => $model->getKey(),
            'user_id' => auth()->id(),
            'payload' => json_encode($model->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Returns newest-first snapshots for an auditable editorial history. */
    public function history(Model $model)
    {
        return DB::table('content_revisions')->where('revisable_type', $model::class)->where('revisable_id', $model->getKey())->latest()->get();
    }

    /** Restores one owned revision after snapshotting the current state for undo support. */
    public function restore(Model $model, int $revisionId): Model
    {
        $revision = DB::table('content_revisions')->where('id', $revisionId)->where('revisable_type', $model::class)->where('revisable_id', $model->getKey())->first();
        if (! $revision) {
            throw new RuntimeException('نسخه موردنظر یافت نشد.');
        }
        $payload = json_decode($revision->payload, true, flags: JSON_THROW_ON_ERROR);
        unset($payload['id'], $payload['created_at'], $payload['updated_at'], $payload['deleted_at']);
        $this->snapshot($model, 'قبل از بازیابی نسخه '.$revisionId);
        $model->fill($payload)->save();

        return $model->fresh();
    }
}
