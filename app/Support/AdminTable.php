<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminTable
{
    /** Applies reusable server-side search, exact filters and whitelisted sorting to an Eloquent admin table query. */
    public function apply(Request $request, Builder $query, array $searchable = [], array $filters = [], array $sortable = [], string $defaultSort = 'id', string $defaultDirection = 'desc'): Builder
    {
        if ($request->filled('q') && $searchable !== []) {
            $term = trim((string) $request->input('q'));
            $query->where(function (Builder $search) use ($searchable, $term): void {
                foreach ($searchable as $index => $column) {
                    $index === 0
                        ? $search->where($column, 'like', '%'.$term.'%')
                        : $search->orWhere($column, 'like', '%'.$term.'%');
                }
            });
        }

        foreach ($filters as $requestKey => $column) {
            if ($request->filled($requestKey)) {
                $query->where($column, $request->input($requestKey));
            }
        }

        $sort = (string) $request->input('sort', $defaultSort);
        $direction = strtolower((string) $request->input('direction', $defaultDirection)) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, $sortable, true)) {
            $sort = $defaultSort;
        }

        return $query->orderBy($sort, $direction);
    }

    /** Uses a bounded page size to prevent expensive arbitrary admin requests. */
    public function perPage(Request $request, int $default = 30): int
    {
        return min(100, max(10, (int) $request->input('per_page', $default)));
    }
}
