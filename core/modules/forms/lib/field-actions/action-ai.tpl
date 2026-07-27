<button type="button"
    class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-2 text-xs font-medium text-neutral-700 shadow-sm hover:border-neutral-400 hover:bg-neutral-100 hover:text-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-300 disabled:pointer-events-none disabled:opacity-50"
    title="[#item.label#]" aria-label="[#item.label#]" x-bind:disabled="busy"
    @click.prevent="ai([#item.field_arg#], lang)">
    [#item.icon_svg#]
    <span>AI</span>
</button>
