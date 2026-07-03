<div class="mb-6 flex flex-wrap items-center gap-2">
    <select onchange="if (this.value) window.location.href='[#base-url#]' + this.value"
        class="rounded border border-neutral-300 bg-white px-3 py-1.5 text-sm text-neutral-700 focus:outline-2 focus:outline-cnormal">
        [#_rs.options#]
    </select>
    <a href="[#base-url#][#_rs.add_url#]"
        class="inline-flex items-center gap-1 rounded-full border border-dashed border-neutral-400 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:border-cnormal hover:text-cnormal">
        + [#text Add#]
    </a>
</div>
