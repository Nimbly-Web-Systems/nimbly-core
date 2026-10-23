<div class="flex size-10 shrink-0 items-center justify-center rounded bg-base-200 text-xs font-semibold uppercase text-neutral-500"
    :class="can_drag() && 'cursor-grab'">
    <template x-if="has_preview(doc)">
        <img class="size-10 rounded" :class="is_pdf(doc) ? 'object-contain' : 'object-cover'" :src="thumb_url(doc, 80, 80)" alt="" loading="lazy" draggable="false" />
    </template>
    <template x-if="!has_preview(doc)">
        <span x-text="extension(doc)"></span>
    </template>
</div>
