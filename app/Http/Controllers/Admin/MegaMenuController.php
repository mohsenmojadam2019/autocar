<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Models\MenuItem;
use App\Domain\Content\Services\MegaMenuService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MegaMenuController extends Controller
{
    /** Shows all main-menu items as an unlimited-depth tree plus scheduling fields. */
    public function index(MegaMenuService $menus): View
    {
        return view('admin.menu.index', ['tree' => $menus->tree('main', 'desktop'), 'items' => MenuItem::query()->where('menu', 'main')->orderBy('position')->get()]);
    }

    /** Creates one database-driven mega-menu item or column. */
    public function store(Request $request, MegaMenuService $menus): RedirectResponse
    {
        $data = $request->validate(['parent_id' => ['nullable', 'exists:menu_items,id'], 'title' => ['required', 'string', 'max:120'], 'type' => ['required', 'in:link,category,column,banner'], 'url' => ['nullable', 'string', 'max:500'], 'icon' => ['nullable', 'string', 'max:120'], 'position' => ['required', 'integer', 'min:0'], 'columns' => ['required', 'integer', 'min:1', 'max:6'], 'mobile_visible' => ['nullable', 'boolean'], 'desktop_visible' => ['nullable', 'boolean'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at']]);
        MenuItem::query()->create($data + ['menu' => 'main']);
        $menus->flush('main');
        return back()->with('success', 'آیتم مگامنو ایجاد شد.');
    }

    /** Deletes a menu branch and invalidates storefront menu caches. */
    public function destroy(MenuItem $item, MegaMenuService $menus): RedirectResponse
    {
        $item->delete();
        $menus->flush('main');
        return back()->with('success', 'آیتم منو حذف شد.');
    }
}
