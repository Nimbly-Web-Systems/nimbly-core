<button type="button"
    class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-neutral-300 disabled:pointer-events-none disabled:opacity-70"
    title="[#item.label#]" aria-label="[#item.label#]" x-bind:disabled="busy"
    @click.prevent="ai([#item.field_arg#], lang)">
    <span class="inline-flex" :class="{ 'animate-spin': ai_busy_field === [#item.field_arg#] }">[#item.icon_svg#]</span>
    <span>[#item.label#]</span>
</button>
