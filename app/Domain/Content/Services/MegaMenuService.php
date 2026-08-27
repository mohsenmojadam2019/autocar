<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MegaMenuService
{
    /** Returns a cached unlimited-depth menu tree filtered for device and scheduling. */
    public function tree(string $menu='main',string $device='desktop'): Collection
    {
        return Cache::remember("mega-menu:{$menu}:{$device}",now()->addMinutes(30),function() use($menu,$device){ $column=$device==='mobile'?'mobile_visible':'desktop_visible'; $items=MenuItem::query()->where('menu',$menu)->where($column,true)->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()))->orderBy('position')->get(); return $this->nest($items); });
    }
    /** Converts a flat menu collection to nested children without fixed depth. */ private function nest(Collection $items,?int $parentId=null): Collection { return $items->where('parent_id',$parentId)->values()->map(function(MenuItem $item) use($items){ $item->setRelation('children',$this->nest($items,$item->id)); return $item; }); }
    /** Invalidates both desktop and mobile menu caches after admin changes. */ public function flush(string $menu='main'): void { Cache::forget("mega-menu:{$menu}:desktop"); Cache::forget("mega-menu:{$menu}:mobile"); }
}
