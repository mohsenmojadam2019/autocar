<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Contracts\PaymentGateway;
use App\Domain\Payment\DTOs\PaymentRequest;
use App\Domain\Payment\DTOs\PaymentVerification;
use App\Domain\Payment\Models\PaymentTransaction;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;

class ConfigurableJsonGateway implements PaymentGateway
{
    public function __construct(private readonly SettingsRepository $settings, private readonly string $gatewayName) {}
    /** Returns the configured provider key such as idpay, zibal, nextpay or payir. */ public function name(): string { return $this->gatewayName; }

    /** Sends a provider-configured JSON request without coupling checkout to one vendor SDK. */
    public function request(Order $order, string $callbackUrl): PaymentRequest
    {
        $base = 'payments.'.$this->gatewayName.'.';
        $endpoint = $this->settings->get($base.'request_url');
        $redirectTemplate = $this->settings->get($base.'redirect_url');
        if (! $endpoint || ! $redirectTemplate) return new PaymentRequest(false,message:'تنظیمات درگاه '.$this->gatewayName.' کامل نیست.');
        $payload = ['api_key'=>$this->settings->get($base.'api_key'),'amount'=>$order->grand_total,'order_id'=>$order->number,'callback'=>$callbackUrl];
        $response = Http::timeout(15)->acceptJson()->post($endpoint,$payload);
        $data = $response->json() ?: [];
        $authority = data_get($data,$this->settings->get($base.'authority_path','id'));
        if (! $response->successful() || ! $authority) return new PaymentRequest(false,payload:$data,message:'ایجاد تراکنش درگاه ناموفق بود.');
        return new PaymentRequest(true,(string)$authority,str_replace('{authority}',(string)$authority,$redirectTemplate),$data);
    }

    /** Verifies configurable JSON gateways with the stored authority and exact amount. */
    public function verify(PaymentTransaction $transaction, array $callback): PaymentVerification
    {
        $base = 'payments.'.$this->gatewayName.'.';
        $endpoint = $this->settings->get($base.'verify_url');
        if (! $endpoint) return new PaymentVerification(false,message:'آدرس Verify درگاه تنظیم نشده است.');
        $response = Http::timeout(15)->acceptJson()->post($endpoint,['api_key'=>$this->settings->get($base.'api_key'),'amount'=>$transaction->amount,'authority'=>$transaction->authority,'callback'=>$callback]);
        $data = $response->json() ?: [];
        $successPath = $this->settings->get($base.'success_path','success');
        $refPath = $this->settings->get($base.'reference_path','track_id');
        return new PaymentVerification((bool)data_get($data,$successPath,false),(string)data_get($data,$refPath,''),$data,$response->successful()?null:'Verify HTTP failed');
    }

    /** Uses an optional configurable refund endpoint and fails closed when it is unavailable. */
    public function refund(PaymentTransaction $transaction, int $amount): PaymentVerification
    {
        $base='payments.'.$this->gatewayName.'.'; $endpoint=$this->settings->get($base.'refund_url');
        if (! $endpoint) return new PaymentVerification(false,message:'Refund برای این درگاه فعال نیست.');
        $response=Http::timeout(15)->acceptJson()->post($endpoint,['api_key'=>$this->settings->get($base.'api_key'),'reference_id'=>$transaction->reference_id,'amount'=>$amount]);
        $data=$response->json()?:[]; return new PaymentVerification($response->successful(),(string)data_get($data,'reference_id',''),$data);
    }
}
