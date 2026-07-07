<div class="mb-6 flex flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:items-center">
    <select onchange="if (this.value) window.location.href='[#base-url#]' + this.value"
        class="select select-bordered min-h-11 w-full text-neutral-700 sm:select-sm sm:min-h-0 sm:w-auto">
        [#_rs.options#]
    </select>
    <a href="[#base-url#][#_rs.add_url#]"
        class="inline-flex min-h-11 items-center justify-center gap-1 rounded-md border border-dashed border-neutral-400 px-3 py-2 text-sm font-medium text-neutral-600 hover:border-cnormal hover:text-cnormal sm:min-h-0 sm:rounded-full sm:py-1.5">
        + [#text Add#]
    </a>
</div>
