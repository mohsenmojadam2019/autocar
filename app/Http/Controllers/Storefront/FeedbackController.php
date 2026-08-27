<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    /** Creates a product review and marks verified purchase only from owned order-item evidence. */
    public function review(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'title' => ['nullable', 'string', 'max:190'], 'body' => ['nullable', 'string', 'max:3000']]);
        $orderItem = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.user_id', $request->user()->id)->where('order_items.product_id', $product->id)->where('orders.status', 'delivered')->select('order_items.id')->latest('order_items.id')->first();
        DB::table('reviews')->insert([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'order_item_id' => $orderItem?->id,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'status' => 'pending',
            'verified_purchase' => $orderItem !== null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'نظر شما برای بررسی ثبت شد.');
    }

    /** Creates a moderated product question for a slug-bound product. */
    public function question(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:2000']]);
        DB::table('product_questions')->insert(['product_id' => $product->id, 'user_id' => $request->user()?->id, 'question' => $data['question'], 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'پرسش شما ثبت شد.');
    }

    /** Reports an approved review for moderator inspection. */
    public function reportReview(Request $request, int $review): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        DB::table('review_reports')->updateOrInsert(['review_id' => $review, 'user_id' => $request->user()?->id], ['reason' => $data['reason'], 'status' => 'pending', 'updated_at' => now(), 'created_at' => now()]);

        return back()->with('success', 'گزارش ثبت شد.');
    }
}
