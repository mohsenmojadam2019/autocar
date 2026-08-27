<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Models\Banner;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Media\LocalMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BannerController extends Controller
{
    /** Lists banners with schedule, placement and click/impression counters. */
    public function index(): View
    {
        $banners = Banner::query()
            ->leftJoin('banner_events', 'banner_events.banner_id', '=', 'banners.id')
            ->select('banners.*')
            ->selectRaw("SUM(CASE WHEN banner_events.event = 'click' THEN 1 ELSE 0 END) AS clicks_count")
            ->selectRaw("SUM(CASE WHEN banner_events.event = 'impression' THEN 1 ELSE 0 END) AS impressions_count")
            ->groupBy('banners.id')
            ->orderBy('banners.placement')
            ->orderBy('banners.position')
            ->paginate(30);

        return view('admin.banners.index', compact('banners'));
    }

    /** Creates a scheduled responsive banner using locally managed, validated image assets. */
    public function store(Request $request, LocalMediaService $media): RedirectResponse
    {
        $data = $this->validated($request, true);
        $desktop = $media->storeImage($request->file('image'), 'banners');
        $mobile = $request->hasFile('mobile_image')
            ? $media->storeImage($request->file('mobile_image'), 'banners/mobile')
            : null;

        Banner::query()->create([
            'name' => $data['name'],
            'placement' => $data['placement'],
            'image_path' => $desktop->variant_path ?: $desktop->path,
            'mobile_image_path' => $mobile?->variant_path ?: $mobile?->path,
            'url' => $data['url'] ?? null,
            'alt' => $data['alt'] ?? $data['name'],
            'position' => $data['position'] ?? 0,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('success', 'بنر ایجاد شد.');
    }

    /** Updates banner metadata and replaces only images explicitly supplied by the administrator. */
    public function update(Request $request, Banner $banner, LocalMediaService $media): RedirectResponse
    {
        $data = $this->validated($request, false);
        $attributes = [
            'name' => $data['name'],
            'placement' => $data['placement'],
            'url' => $data['url'] ?? null,
            'alt' => $data['alt'] ?? $data['name'],
            'position' => $data['position'] ?? 0,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];

        if ($request->hasFile('image')) {
            $attributes['image_path'] = $this->replaceManagedImage($banner->image_path, $request->file('image'), 'banners', $media);
        }
        if ($request->hasFile('mobile_image')) {
            $attributes['mobile_image_path'] = $this->replaceManagedImage($banner->mobile_image_path, $request->file('mobile_image'), 'banners/mobile', $media);
        }

        $banner->update($attributes);

        return back()->with('success', 'بنر ذخیره شد.');
    }

    /** Toggles banner activation while retaining schedule and analytics history. */
    public function toggle(Banner $banner): RedirectResponse
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return back()->with('success', 'وضعیت بنر تغییر کرد.');
    }

    /** Deletes a banner, its analytics and locally managed media where ownership can be resolved. */
    public function destroy(Banner $banner, LocalMediaService $media): RedirectResponse
    {
        DB::transaction(function () use ($banner, $media): void {
            foreach (array_filter([$banner->image_path, $banner->mobile_image_path]) as $path) {
                $this->deleteManagedImage($path, $media);
            }
            $banner->delete();
        });

        return back()->with('success', 'بنر حذف شد.');
    }

    /** Validates responsive banner metadata and date windows. */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'placement' => ['required', 'string', 'max:64'],
            'image' => [$creating ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'mobile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'url' => ['nullable', 'string', 'max:1000'],
            'alt' => ['nullable', 'string', 'max:250'],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /** Stores a replacement image then cleans up the previous managed file if possible. */
    private function replaceManagedImage(?string $oldPath, $file, string $directory, LocalMediaService $media): string
    {
        $asset = $media->storeImage($file, $directory);
        if ($oldPath) {
            $this->deleteManagedImage($oldPath, $media);
        }

        return $asset->variant_path ?: $asset->path;
    }

    /** Removes a media asset through metadata when available or deletes only the physical public file as fallback. */
    private function deleteManagedImage(string $path, LocalMediaService $media): void
    {
        $asset = MediaAsset::query()->where('path', $path)->orWhere('variant_path', $path)->first();
        if ($asset) {
            $media->delete($asset);
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
