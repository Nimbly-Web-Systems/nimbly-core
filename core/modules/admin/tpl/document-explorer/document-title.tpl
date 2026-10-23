<a class="block truncate font-medium text-neutral-800 hover:underline" :href="view_url(doc)" x-text="doc.title || doc.name"></a>
<div class="truncate text-xs text-neutral-500">
    <span x-text="doc.name"></span>
    <template x-if="category === '' && config.categories[doc.category]">
        <span> · <span x-text="config.categories[doc.category]"></span></span>
    </template>
</div>
