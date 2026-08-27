<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SearchSeoController extends Controller
{
    /** Shows search synonyms, recent query analytics and SEO redirects. */
    public function index(): View
    {
        return view('admin.search-seo.index', [
            'synonyms' => DB::table('search_synonyms')->orderBy('term')->get(),
            'searches' => DB::table('search_histories')->selectRaw('term, COUNT(*) as searches, AVG(results_count) as avg_results')->groupBy('term')->orderByDesc('searches')->limit(100)->get(),
            'redirects' => DB::table('seo_redirects')->orderByDesc('hits')->orderBy('from_path')->get(),
        ]);
    }

    /** Creates a bidirectional runtime synonym mapping. */
    public function storeSynonym(Request $request): RedirectResponse
    {
        $data = $request->validate(['term' => ['required', 'string', 'max:190'], 'synonym' => ['required', 'string', 'max:190'], 'weight' => ['nullable', 'integer', 'min:1', 'max:255']]);
        DB::table('search_synonyms')->updateOrInsert(
            ['term' => trim($data['term']), 'synonym' => trim($data['synonym'])],
            ['weight' => $data['weight'] ?? 100, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        return back()->with('success', 'مترادف جست‌وجو ذخیره شد.');
    }

    /** Deletes a synonym mapping. */
    public function destroySynonym(int $synonym): RedirectResponse
    {
        DB::table('search_synonyms')->where('id', $synonym)->delete();

        return back()->with('success', 'مترادف حذف شد.');
    }

    /** Creates or updates a safe SEO redirect. */
    public function storeRedirect(Request $request): RedirectResponse
    {
        $data = $request->validate(['from_path' => ['required', 'string', 'max:500'], 'to_url' => ['required', 'string', 'max:1000'], 'status_code' => ['required', Rule::in([301, 302, 307, 308])]]);
        $from = '/'.ltrim(parse_url($data['from_path'], PHP_URL_PATH) ?: $data['from_path'], '/');
        abort_if($from === '/' || str_starts_with($from, '/admin') || str_starts_with($from, '/api'), 422, 'مسیر Redirect مجاز نیست.');
        DB::table('seo_redirects')->updateOrInsert(['from_path' => $from], ['to_url' => $data['to_url'], 'status_code' => $data['status_code'], 'is_active' => true, 'updated_at' => now(), 'created_at' => DB::table('seo_redirects')->where('from_path', $from)->value('created_at') ?: now()]);

        return back()->with('success', 'Redirect ذخیره شد.');
    }

    /** Toggles an SEO redirect without deleting its analytics. */
    public function toggleRedirect(int $redirect): RedirectResponse
    {
        $row = DB::table('seo_redirects')->where('id', $redirect)->first();
        abort_unless($row, 404);
        DB::table('seo_redirects')->where('id', $redirect)->update(['is_active' => ! $row->is_active, 'updated_at' => now()]);

        return back()->with('success', 'وضعیت Redirect تغییر کرد.');
    }
}
