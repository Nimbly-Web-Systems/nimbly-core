<div class="flex size-10 shrink-0 items-center justify-center rounded bg-base-200 text-xs font-semibold uppercase text-neutral-500"
    :class="[can_drag() && 'cursor-grab', { 'text-error': family(doc) === 'pdf', 'text-info': family(doc) === 'text', 'text-success': family(doc) === 'sheet', 'text-warning': family(doc) === 'slides', 'text-secondary': family(doc) === 'design' }]">
    <span x-text="extension(doc)"></span>
</div>
