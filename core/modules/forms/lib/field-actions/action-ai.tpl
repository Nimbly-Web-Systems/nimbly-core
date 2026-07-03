<button type="button"
    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-300 disabled:pointer-events-none disabled:opacity-50"
    title="[#item.label#]" aria-label="[#item.label#]" x-bind:disabled="busy"
    @click.prevent="ai([#item.field_arg#], lang)">
    [#item.icon_svg#]
</button>
