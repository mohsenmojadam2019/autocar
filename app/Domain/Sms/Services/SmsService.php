<?php

namespace App\Domain\Sms\Services;

use App\Domain\Sms\Contracts\SmsProvider;
use App\Domain\Sms\Providers\KavenegarProvider;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SmsService
{
    public function __construct(private readonly SettingsRepository $settings) {}
    /** Sends and logs a transactional SMS with provider response separated from customer-facing data. */ public function send(string $mobile,string $body,?int $userId=null,?string $templateKey=null): int { $provider=$this->provider(); $id=DB::table('sms_messages')->insertGetId(['user_id'=>$userId,'provider'=>$provider->name(),'mobile'=>$mobile,'template_key'=>$templateKey,'body'=>$body,'status'=>'sending','created_at'=>now(),'updated_at'=>now()]); try{$result=$provider->send($mobile,$body); DB::table('sms_messages')->where('id',$id)->update(['status'=>'sent','provider_message_id'=>$result['id']??null,'meta'=>json_encode($result['response']??[],JSON_UNESCAPED_UNICODE),'sent_at'=>now(),'updated_at'=>now()]);}catch(\Throwable $e){DB::table('sms_messages')->where('id',$id)->update(['status'=>'failed','error'=>$e->getMessage(),'updated_at'=>now()]); throw $e;} return $id; }
    /** Resolves the active provider; additional providers use the same contract and can be registered later without changing callers. */ public function provider(): SmsProvider { return match($this->settings->get('sms.default_provider','kavenegar')){'kavenegar'=>app(KavenegarProvider::class),default=>throw new RuntimeException('درگاه پیامک پشتیبانی نمی‌شود.')}; }
}
