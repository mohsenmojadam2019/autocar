<?php

namespace App\Domain\Customer\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\CompareList;
use App\Domain\Customer\Models\Wishlist;
use Illuminate\Support\Str;
use RuntimeException;

class WishlistCompareService
{
    /** Returns or creates the default wishlist for an authenticated customer. */
    public function wishlist(int $userId): Wishlist
    {
        return Wishlist::query()->firstOrCreate(['user_id' => $userId, 'is_default' => true], [
            'name' => 'علاقه‌مندی‌ها',
            'share_token' => Str::random(40),
        ]);
    }

    /** Adds a slug-bound product to the default wishlist idempotently. */
    public function addWishlist(int $userId, Product $product): Wishlist
    {
        $wishlist = $this->wishlist($userId);
        $wishlist->products()->syncWithoutDetaching([$product->id]);

        return $wishlist->fresh('products');
    }

    /** Removes a product from the authenticated customer's default wishlist. */
    public function removeWishlist(int $userId, Product $product): Wishlist
    {
        $wishlist = $this->wishlist($userId);
        $wishlist->products()->detach($product->id);

        return $wishlist->fresh('products');
    }

    /** Resolves a guest/member comparison list by opaque browser token. */
    public function compare(?int $userId, ?string $sessionToken): CompareList
    {
        $query = CompareList::query();
        if ($userId !== null && ($existing = $query->where('user_id', $userId)->latest()->first())) {
            return $existing;
        }
        if ($sessionToken && ($existing = $query->where('session_token', $sessionToken)->first())) {
            if ($userId !== null && $existing->user_id === null) {
                $existing->update(['user_id' => $userId]);
            }

            return $existing;
        }

        return CompareList::query()->create([
            'user_id' => $userId,
            'session_token' => $sessionToken ?: Str::random(48),
            'share_token' => Str::random(40),
        ]);
    }

    /** Adds a product to comparison while enforcing a focused four-product matrix. */
    public function addCompare(CompareList $list, Product $product): CompareList
    {
        if (! $list->products()->whereKey($product->id)->exists() && $list->products()->count() >= 4) {
            throw new RuntimeException('حداکثر چهار محصول قابل مقایسه است.');
        }
        $list->products()->syncWithoutDetaching([$product->id]);

        return $list->fresh('products');
    }

    /** Removes a product from a comparison list. */
    public function removeCompare(CompareList $list, Product $product): CompareList
    {
        $list->products()->detach($product->id);

        return $list->fresh('products');
    }
}
