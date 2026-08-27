<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModerationController extends Controller
{
    /** Shows pending reviews, abuse reports and product questions. */
    public function index(): View
    {
        return view('admin.content.moderation', [
            'reviews' => DB::table('reviews')->join('products', 'products.id', '=', 'reviews.product_id')->leftJoin('users', 'users.id', '=', 'reviews.user_id')->select('reviews.*', 'products.name as product_name', 'users.name as user_name')->whereIn('reviews.status', ['pending', 'approved'])->latest('reviews.id')->paginate(25, ['*'], 'reviews_page'),
            'reports' => DB::table('review_reports')->join('reviews', 'reviews.id', '=', 'review_reports.review_id')->where('review_reports.status', 'pending')->select('review_reports.*', 'reviews.body as review_body')->latest('review_reports.id')->get(),
            'questions' => DB::table('product_questions')->join('products', 'products.id', '=', 'product_questions.product_id')->select('product_questions.*', 'products.name as product_name')->latest('product_questions.id')->paginate(25, ['*'], 'questions_page'),
        ]);
    }

    /** Approves/rejects a review and optionally stores the official seller reply. */
    public function review(Request $request, int $review): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'admin_reply' => ['nullable', 'string', 'max:3000']]);
        DB::table('reviews')->where('id', $review)->update(['status' => $data['status'], 'admin_reply' => $data['admin_reply'] ?? null, 'updated_at' => now()]);

        return back()->with('success', 'نظر Moderation شد.');
    }

    /** Resolves an abuse report with a moderator note. */
    public function report(Request $request, int $report): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['resolved', 'dismissed'])], 'moderator_note' => ['nullable', 'string', 'max:2000']]);
        DB::table('review_reports')->where('id', $report)->update($data + ['updated_at' => now()]);

        return back()->with('success', 'گزارش بررسی شد.');
    }

    /** Answers and publishes or rejects a product question. */
    public function question(Request $request, int $question): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'answer' => ['nullable', 'required_if:status,approved', 'string', 'max:5000']]);
        DB::table('product_questions')->where('id', $question)->update(['status' => $data['status'], 'answer' => $data['answer'] ?? null, 'updated_at' => now()]);

        return back()->with('success', 'پرسش بررسی شد.');
    }
}
