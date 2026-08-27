<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CategoryTreeService
{
    /** Returns the complete unlimited-depth category tree with eager-loaded descendants. */
    public function tree(bool $onlyVisible = false): Collection
    {
        $query = Category::query()->whereNull('parent_id')->orderBy('position');

        if ($onlyVisible) {
            $query->visible();
        }

        return $query->get()->each(fn (Category $category) => $this->loadDescendants($category, $onlyVisible));
    }

    /** Moves a category while preventing circular ancestry and recalculating descendant depth. */
    public function move(Category $category, ?Category $parent, int $position = 0): Category
    {
        if ($parent && ($parent->is($category) || $this->isDescendantOf($parent, $category))) {
            throw new InvalidArgumentException('A category cannot be moved below itself or one of its descendants.');
        }

        return DB::transaction(function () use ($category, $parent, $position): Category {
            $category->update([
                'parent_id' => $parent?->getKey(),
                'depth' => $parent ? $parent->depth + 1 : 0,
                'position' => max(0, $position),
            ]);

            $this->recalculateDescendantDepths($category->fresh());

            return $category->fresh();
        });
    }

    /** Loads descendants recursively without imposing an artificial category depth limit. */
    private function loadDescendants(Category $category, bool $onlyVisible): void
    {
        $children = $category->children();
        if ($onlyVisible) {
            $children->visible();
        }

        $category->setRelation('children', $children->get()->each(
            fn (Category $child) => $this->loadDescendants($child, $onlyVisible)
        ));
    }

    /** Checks whether the candidate category is below the supplied ancestor. */
    private function isDescendantOf(Category $candidate, Category $ancestor): bool
    {
        $cursor = $candidate;
        while ($cursor->parent_id) {
            if ((int) $cursor->parent_id === (int) $ancestor->getKey()) {
                return true;
            }
            $cursor = $cursor->parent()->first();
            if (! $cursor) {
                break;
            }
        }

        return false;
    }

    /** Recalculates depth values after a branch is moved in the category tree. */
    private function recalculateDescendantDepths(Category $category): void
    {
        foreach ($category->children()->get() as $child) {
            $child->update(['depth' => $category->depth + 1]);
            $this->recalculateDescendantDepths($child);
        }
    }
}
