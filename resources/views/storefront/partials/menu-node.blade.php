@php($children=$node->children ?? collect())
<li class="mega-node @if($children->isNotEmpty()) has-children @endif">
    <a href="{{ $node->url ?: '#' }}" @if($children->isNotEmpty()) aria-haspopup="true" @endif>
        @if($node->icon)<i class="{{ $node->icon }}" aria-hidden="true"></i>@endif
        <span>{{ $node->title }}</span>
    </a>
    @if($children->isNotEmpty())
        <ul class="mega-submenu">
            @foreach($children as $child)
                @include('storefront.partials.menu-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
