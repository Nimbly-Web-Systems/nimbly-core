<button type="button"
    class="inline-flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-md border border-neutral-300 bg-white text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-300 disabled:pointer-events-none disabled:opacity-40"
    title="[#item.label#]" aria-label="[#item.label#]" x-bind:disabled="busy || !translation_field_empty([#item.field_arg#], lang)"
    @click.prevent="ai([#item.field_arg#], lang)">
    <span class="inline-flex" :class="{ 'animate-spin': ai_busy_field === [#item.field_arg#] }">[#item.icon_svg#]</span>
</button>
