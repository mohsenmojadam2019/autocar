<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Services\CatalogTransferService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CatalogOperationsController extends Controller
{
    /** Shows import history and downloadable row-level diagnostics. */
    public function index(): View
    {
        $imports = DB::table('catalog_imports')->latest()->paginate(30);

        return view('admin.catalog-operations.index', compact('imports'));
    }

    /** Imports CSV through the domain service; products and taxonomy are addressed by slug. */
    public function import(Request $request, CatalogTransferService $transfer): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $path = $data['file']->storeAs('imports', now()->format('Ymd-His').'-catalog.csv', 'local');
        $importId = $transfer->importCsv(Storage::disk('local')->path($path), $request->user()->id);

        return back()->with('success', 'Import #'.$importId.' پردازش شد.');
    }

    /** Streams a newly generated slug-safe catalog CSV. */
    public function export(CatalogTransferService $transfer): BinaryFileResponse
    {
        $path = $transfer->exportCsv();

        return response()->download(Storage::disk('local')->path($path), basename($path));
    }

    /** Applies whitelisted mass updates to products selected exclusively by product slug. */
    public function bulk(Request $request, CatalogTransferService $transfer): RedirectResponse
    {
        $data = $request->validate([
            'product_slugs' => ['required', 'array', 'min:1'],
            'product_slugs.*' => ['string', 'exists:products,slug'],
            'status' => ['nullable', 'in:draft,active,out_of_stock,discontinued,archived'],
            'sale_price' => ['nullable', 'integer', 'min:0'],
            'compare_at_price' => ['nullable', 'integer', 'min:0'],
            'wholesale_price' => ['nullable', 'integer', 'min:0'],
        ]);
        $changes = array_filter(array_intersect_key($data, array_flip(['status', 'sale_price', 'compare_at_price', 'wholesale_price'])), fn ($value) => $value !== null && $value !== '');
        $count = $transfer->bulkUpdate($data['product_slugs'], $changes);

        return back()->with('success', number_format($count).' محصول بروزرسانی شد.');
    }

    /** Shows row-level errors for one import job. */
    public function errors(int $import): View
    {
        $job = DB::table('catalog_imports')->find($import);
        abort_unless($job, 404);
        $errors = DB::table('catalog_import_errors')->where('catalog_import_id', $import)->paginate(100);

        return view('admin.catalog-operations.errors', compact('job', 'errors'));
    }
}
