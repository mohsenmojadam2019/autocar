<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MegaMenuService
{
    /** Returns a cached unlimited-depth menu tree filtered for device and scheduling. */
    public function tree(string $menu = 'main', string $device = 'desktop'): Collection
    {
        $cacheKey = "mega-menu:{$menu}:{$device}:v2";
        $nodes = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($menu, $device): array {
            $column = $device === 'mobile' ? 'mobile_visible' : 'desktop_visible';
            $items = MenuItem::query()
                ->where('menu', $menu)
                ->where($column, true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->orderBy('position')
                ->get();

            return $this->nest($items)->map(fn (MenuItem $item): array => $this->serializeNode($item))->all();
        });

        return collect(is_array($nodes) ? $nodes : [])->map(fn (array $node): MenuItem => $this->hydrateNode($node));
    }

    /** Converts a flat menu collection to nested children without fixed depth. */
    private function nest(Collection $items, ?int $parentId = null): Collection
    {
        return $items->where('parent_id', $parentId)->values()->map(function (MenuItem $item) use ($items) {
            $item->setRelation('children', $this->nest($items, $item->id));

            return $item;
        });
    }

    /** Converts Eloquent nodes to cache-safe scalar arrays so every cache driver behaves identically. */
    private function serializeNode(MenuItem $item): array
    {
        return [
            'attributes' => $item->getAttributes(),
            'children' => $item->children->map(fn (MenuItem $child): array => $this->serializeNode($child))->all(),
        ];
    }

    /** Rehydrates cached scalar nodes for the existing Blade object API without serializing model instances. */
    private function hydrateNode(array $node): MenuItem
    {
        $item = new MenuItem;
        $item->setRawAttributes((array) ($node['attributes'] ?? []), true);
        $item->exists = true;
        $item->setRelation('children', collect($node['children'] ?? [])->map(fn (array $child): MenuItem => $this->hydrateNode($child)));

        return $item;
    }

    /** Invalidates both desktop and mobile menu caches after admin changes. */
    public function flush(string $menu = 'main'): void
    {
        foreach (['desktop', 'mobile'] as $device) {
            Cache::forget("mega-menu:{$menu}:{$device}");
            Cache::forget("mega-menu:{$menu}:{$device}:v2");
        }
    }
}
