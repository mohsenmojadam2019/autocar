<?php

namespace App\Domain\Marketing\Services;

use App\Domain\Sms\Services\SmsService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CampaignService
{
    public function __construct(private readonly SmsService $sms) {}

    /** Materializes recipients only from active users with explicit SMS marketing consent. */
    public function buildRecipients(int $campaignId): int
    {
        $campaign = DB::table('sms_campaigns')->find($campaignId);
        if (! $campaign) {
            throw new RuntimeException('کمپین یافت نشد.');
        }
        $query = DB::table('users')
            ->join('marketing_consents', function ($join): void {
                $join->on('marketing_consents.user_id', '=', 'users.id')
                    ->where('marketing_consents.channel', 'sms')
                    ->where('marketing_consents.granted', true);
            })
            ->whereNotNull('users.mobile')->where('users.is_active', true)->select('users.id', 'users.mobile');

        if ($campaign->segment_id) {
            $rules = json_decode(DB::table('customer_segments')->where('id', $campaign->segment_id)->value('rules') ?? '[]', true) ?: [];
            if (isset($rules['customer_group'])) {
                $query->join('customer_profiles', 'customer_profiles.user_id', '=', 'users.id')->where('customer_profiles.customer_group', $rules['customer_group']);
            }
            if (isset($rules['min_lifetime_value'])) {
                $query->join('customer_profiles as cp2', 'cp2.user_id', '=', 'users.id')->where('cp2.lifetime_value', '>=', (int) $rules['min_lifetime_value']);
            }
        }

        $count = 0;
        foreach ($query->orderBy('users.id')->cursor() as $user) {
            DB::table('sms_campaign_recipients')->updateOrInsert(
                ['sms_campaign_id' => $campaignId, 'mobile' => $user->mobile],
                ['user_id' => $user->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            );
            $count++;
        }

        return $count;
    }

    /** Marks a campaign stopped immediately so every send iteration fails closed. */
    public function stop(int $campaignId): void
    {
        DB::table('sms_campaigns')->where('id', $campaignId)->update(['status' => 'stopped', 'finished_at' => now(), 'updated_at' => now()]);
    }

    /** Processes due campaigns once per minute while respecting consent, stop state and configured rate. */
    public function processDue(): int
    {
        $campaigns = DB::table('sms_campaigns')
            ->whereIn('status', ['scheduled', 'running', 'draft'])
            ->where(fn ($query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->orderBy('id')->get();
        $sent = 0;

        foreach ($campaigns as $campaign) {
            if ($campaign->status === 'draft') {
                $recipientCount = DB::table('sms_campaign_recipients')->where('sms_campaign_id', $campaign->id)->count();
                if ($recipientCount === 0) {
                    continue;
                }
            }
            DB::table('sms_campaigns')->where('id', $campaign->id)->whereNot('status', 'stopped')->update([
                'status' => 'running',
                'started_at' => $campaign->started_at ?: now(),
                'updated_at' => now(),
            ]);
            $limit = min(max((int) $campaign->rate_per_minute, 1), 600);
            $recipients = DB::table('sms_campaign_recipients')
                ->where('sms_campaign_id', $campaign->id)->where('status', 'pending')->orderBy('id')->limit($limit)->get();

            foreach ($recipients as $recipient) {
                $freshStatus = DB::table('sms_campaigns')->where('id', $campaign->id)->value('status');
                if ($freshStatus === 'stopped') {
                    break;
                }
                $consented = $recipient->user_id
                    ? DB::table('marketing_consents')->where('user_id', $recipient->user_id)->where('channel', 'sms')->where('granted', true)->exists()
                    : false;
                if (! $consented) {
                    DB::table('sms_campaign_recipients')->where('id', $recipient->id)->update(['status' => 'skipped_no_consent', 'updated_at' => now()]);

                    continue;
                }
                try {
                    $messageId = $this->sms->send($recipient->mobile, $campaign->message, $recipient->user_id, 'campaign');
                    DB::table('sms_campaign_recipients')->where('id', $recipient->id)->update(['status' => 'sent', 'sms_message_id' => $messageId, 'updated_at' => now()]);
                    DB::table('sms_campaigns')->where('id', $campaign->id)->increment('sent_count');
                    $sent++;
                } catch (Throwable $exception) {
                    DB::table('sms_campaign_recipients')->where('id', $recipient->id)->update(['status' => 'failed', 'updated_at' => now()]);
                    DB::table('sms_campaigns')->where('id', $campaign->id)->increment('failed_count');
                }
            }

            $pending = DB::table('sms_campaign_recipients')->where('sms_campaign_id', $campaign->id)->where('status', 'pending')->exists();
            if (! $pending && DB::table('sms_campaigns')->where('id', $campaign->id)->value('status') !== 'stopped') {
                DB::table('sms_campaigns')->where('id', $campaign->id)->update(['status' => 'completed', 'finished_at' => now(), 'updated_at' => now()]);
            }
        }

        return $sent;
    }
}
