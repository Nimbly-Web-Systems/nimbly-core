<div class="mb-6 flex flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:items-center">
    <select onchange="if (this.value) window.location.href='[#base-url#]' + this.value"
        class="select select-bordered min-h-11 w-full text-neutral-700 sm:select-sm sm:min-h-0 sm:w-auto">
        [#_rs.options#]
    </select>
    [#_rs.add#]
</div>
