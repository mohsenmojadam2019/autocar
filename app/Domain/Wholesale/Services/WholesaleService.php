<?php

namespace App\Domain\Wholesale\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Promotion\Services\PricingService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WholesaleService
{
    public function __construct(private readonly PricingService $pricing) {}

    public function apply(User $user, array $data): int
    {
        DB::table('wholesale_accounts')->updateOrInsert(['user_id' => $user->id], [
            'status' => 'pending', 'discount_percent' => 0, 'credit_limit' => 0,
            'company_name' => $data['company_name'] ?? $user->legal_name, 'tax_id' => $data['tax_id'] ?? $user->national_id,
            'updated_at' => now(), 'created_at' => DB::table('wholesale_accounts')->where('user_id', $user->id)->value('created_at') ?: now(),
        ]);
        return (int) DB::table('wholesale_accounts')->where('user_id', $user->id)->value('id');
    }

    public function reviewAccount(int $accountId, string $status, int $discountPercent = 0, int $creditLimit = 0): void
    {
        if (! in_array($status, ['approved', 'rejected', 'blocked'], true)) {
            throw new RuntimeException('وضعیت حساب عمده معتبر نیست.');
        }
        DB::table('wholesale_accounts')->where('id', $accountId)->update(['status' => $status, 'discount_percent' => min(max($discountPercent, 0), 100), 'credit_limit' => max(0, $creditLimit), 'updated_at' => now()]);
    }

    /** Creates a B2B quote using exactly the same authoritative pricing engine as cart/checkout. */
    public function quote(User $user, array $items, ?string $note = null, int $validDays = 7): int
    {
        $account = DB::table('wholesale_accounts')->where('user_id', $user->id)->where('status', 'approved')->first();
        if (! $account) {
            throw new RuntimeException('حساب فروش عمده تأیید نشده است.');
        }
        if ($items === []) {
            throw new RuntimeException('حداقل یک قلم برای استعلام عمده لازم است.');
        }

        return DB::transaction(function () use ($user, $items, $note, $validDays): int {
            $rows = [];
            $subtotal = 0;
            $discountTotal = 0;
            foreach ($items as $item) {
                $product = Product::query()->published()->where('slug', $item['product_slug'])->firstOrFail();
                $quantity = max((int) $product->minimum_order_quantity, (int) $item['quantity'], 1);
                $price = $this->pricing->price($product, null, $quantity, $user->id, false);
                $retail = (int) $price['base_price'];
                $unit = (int) $price['final_price'];
                $subtotal += $retail * $quantity;
                $discountTotal += max(0, ($retail - $unit) * $quantity);
                $rows[] = compact('product', 'quantity', 'unit');
            }
            $quoteId = DB::table('wholesale_quotes')->insertGetId([
                'number' => 'WQ-'.now()->format('ymd').'-'.random_int(10000, 99999), 'user_id' => $user->id, 'status' => 'quoted',
                'subtotal' => $subtotal, 'discount_total' => $discountTotal, 'total' => $subtotal - $discountTotal,
                'expires_at' => now()->addDays(min(max($validDays, 1), 30)), 'note' => $note, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($rows as $row) {
                DB::table('wholesale_quote_items')->insert(['wholesale_quote_id' => $quoteId, 'product_id' => $row['product']->id, 'product_variant_id' => null, 'quantity' => $row['quantity'], 'unit_price' => $row['unit'], 'line_total' => $row['unit'] * $row['quantity'], 'created_at' => now(), 'updated_at' => now()]);
            }
            return $quoteId;
        });
    }
}
