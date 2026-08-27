<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    /** Credits a wallet and appends an immutable ledger entry under a row lock. */ public function credit(Wallet $wallet,int $amount,string $description,?string $refType=null,?int $refId=null): Wallet { return $this->apply($wallet,abs($amount),'credit',$description,$refType,$refId); }
    /** Debits available wallet balance and fails atomically when funds are insufficient. */ public function debit(Wallet $wallet,int $amount,string $description,?string $refType=null,?int $refId=null): Wallet { return $this->apply($wallet,-abs($amount),'debit',$description,$refType,$refId); }
    /** Performs the locked wallet balance mutation and ledger append. */ private function apply(Wallet $wallet,int $delta,string $type,string $description,?string $refType,?int $refId): Wallet { return DB::transaction(function() use($wallet,$delta,$type,$description,$refType,$refId){ $locked=Wallet::query()->lockForUpdate()->findOrFail($wallet->id); if($locked->balance+$delta<0) throw new RuntimeException('اعتبار کیف پول کافی نیست.'); $locked->balance+=$delta; $locked->save(); $locked->entries()->create(['type'=>$type,'amount'=>$delta,'balance_after'=>$locked->balance,'reference_type'=>$refType,'reference_id'=>$refId,'description'=>$description,'created_at'=>now()]); return $locked->fresh(); }); }
}
