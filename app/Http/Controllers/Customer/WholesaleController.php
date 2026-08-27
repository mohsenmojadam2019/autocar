<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Wholesale\Services\WholesaleDocumentService;
use App\Domain\Wholesale\Services\WholesaleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WholesaleController extends Controller
{
    /** Shows the customer's wholesale account and quotes. */
    public function index(Request $request): View
    {
        return view('customer.wholesale.index', [
            'account' => DB::table('wholesale_accounts')->where('user_id', $request->user()->id)->first(),
            'quotes' => DB::table('wholesale_quotes')->where('user_id', $request->user()->id)->latest('id')->get(),
            'products' => DB::table('products')->whereNull('deleted_at')->where('status', 'active')->orderBy('name')->limit(300)->get(['slug', 'name', 'sku', 'wholesale_price', 'sale_price']),
        ]);
    }

    /** Submits or refreshes a wholesale application. */
    public function apply(Request $request, WholesaleService $wholesale): RedirectResponse
    {
        $data = $request->validate(['company_name' => ['required', 'string', 'max:190'], 'tax_id' => ['nullable', 'string', 'max:30']]);
        $wholesale->apply($request->user(), $data);

        return back()->with('success', 'درخواست همکاری عمده ثبت شد.');
    }

    /** Creates a quote from product slugs. */
    public function quote(Request $request, WholesaleService $wholesale): RedirectResponse
    {
        $data = $request->validate(['items' => ['required', 'array', 'min:1'], 'items.*.product_slug' => ['required', 'exists:products,slug'], 'items.*.quantity' => ['required', 'integer', 'min:1'], 'note' => ['nullable', 'string', 'max:2000']]);
        $wholesale->quote($request->user(), $data['items'], $data['note'] ?? null);

        return back()->with('success', 'پیش‌فاکتور عمده ایجاد شد.');
    }

    /** Streams an owned wholesale proforma PDF. */
    public function proforma(Request $request, string $number, WholesaleDocumentService $documents): Response
    {
        $quote = DB::table('wholesale_quotes')->where('number', $number)->where('user_id', $request->user()->id)->first();
        abort_unless($quote, 404);

        return response($documents->proforma((int) $quote->id), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="proforma-'.$number.'.pdf"']);
    }
}
