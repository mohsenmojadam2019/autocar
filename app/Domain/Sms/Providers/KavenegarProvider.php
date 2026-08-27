<?php

namespace App\Domain\Sms\Providers;

use App\Domain\Sms\Contracts\SmsProvider;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KavenegarProvider implements SmsProvider
{
    public function __construct(private readonly SettingsRepository $settings) {}
    /** Returns provider key. */ public function name(): string { return 'kavenegar'; }
    /** Sends a normal Kavenegar SMS through the REST endpoint using encrypted API key settings. */ public function send(string $mobile,string $message,array $meta=[]): array { $key=$this->settings->get('sms.kavenegar.api_key'); if(!$key) throw new RuntimeException('کلید کاوه‌نگار تنظیم نشده است.'); $res=Http::timeout(15)->asForm()->post("https://api.kavenegar.com/v1/{$key}/sms/send.json",['receptor'=>$mobile,'message'=>$message,'sender'=>$this->settings->get('sms.kavenegar.sender')]); $data=$res->json()?:[]; if(!$res->successful()) throw new RuntimeException('ارسال پیامک کاوه‌نگار ناموفق بود.'); return ['id'=>(string)data_get($data,'entries.0.messageid',''),'response'=>$data]; }
    /** Sends a Kavenegar verify/lookup pattern with up to three token values. */ public function sendPattern(string $mobile,string $pattern,array $variables): array { $key=$this->settings->get('sms.kavenegar.api_key'); if(!$key) throw new RuntimeException('کلید کاوه‌نگار تنظیم نشده است.'); $payload=['receptor'=>$mobile,'template'=>$pattern,'token'=>$variables['token']??reset($variables)]; if(isset($variables['token2']))$payload['token2']=$variables['token2']; if(isset($variables['token3']))$payload['token3']=$variables['token3']; $res=Http::timeout(15)->asForm()->post("https://api.kavenegar.com/v1/{$key}/verify/lookup.json",$payload); $data=$res->json()?:[]; if(!$res->successful()) throw new RuntimeException('ارسال الگوی کاوه‌نگار ناموفق بود.'); return ['id'=>(string)data_get($data,'entries.0.messageid',''),'response'=>$data]; }
}
