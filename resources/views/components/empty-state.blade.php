@props(['icon' => 'inbox', 'title'])
<div class="empty-state">
    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    <p class="empty-title mb-1">{{ $title }}</p>
    @if ($slot->isNotEmpty())
        <div class="mx-auto prose">{{ $slot }}</div>
    @endif
</div>
