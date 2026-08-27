<?php

namespace App\Domain\Marketing\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CampaignService
{
    /** Materializes a campaign recipient list only from users with explicit SMS marketing consent. */
    public function buildRecipients(int $campaignId): int
    {
        $campaign=DB::table('sms_campaigns')->find($campaignId); if(!$campaign) throw new RuntimeException('کمپین یافت نشد.');
        $query=DB::table('users')->join('marketing_consents',function($join){$join->on('marketing_consents.user_id','=','users.id')->where('marketing_consents.channel','sms')->where('marketing_consents.granted',true);})->whereNotNull('users.mobile')->where('users.is_active',true)->select('users.id','users.mobile');
        if($campaign->segment_id){ $rules=json_decode(DB::table('customer_segments')->where('id',$campaign->segment_id)->value('rules')??'[]',true); if(isset($rules['customer_group'])) $query->join('customer_profiles','customer_profiles.user_id','=','users.id')->where('customer_profiles.customer_group',$rules['customer_group']); if(isset($rules['min_lifetime_value'])) $query->join('customer_profiles as cp2','cp2.user_id','=','users.id')->where('cp2.lifetime_value','>=',(int)$rules['min_lifetime_value']); }
        $count=0; foreach($query->orderBy('users.id')->cursor() as $user){ DB::table('sms_campaign_recipients')->updateOrInsert(['sms_campaign_id'=>$campaignId,'mobile'=>$user->mobile],['user_id'=>$user->id,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]); $count++; }
        return $count;
    }

    /** Marks a campaign stopped immediately so queued send workers can fail closed before the next send. */
    public function stop(int $campaignId): void { DB::table('sms_campaigns')->where('id',$campaignId)->update(['status'=>'stopped','finished_at'=>now(),'updated_at'=>now()]); }
}
