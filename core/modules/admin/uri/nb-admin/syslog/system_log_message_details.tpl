<details class="group max-w-3xl">
    <summary class="cursor-pointer list-none rounded px-1 py-0.5 hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-primary">
        [#fmt var="record.summary" type=text#]
        <span class="ml-1 text-xs text-neutral-400 group-open:hidden">[#text Show details#]</span>
        <span class="ml-1 hidden text-xs text-neutral-400 group-open:inline">[#text Hide details#]</span>
    </summary>
    <div class="mt-2 whitespace-pre-wrap break-all rounded bg-neutral-100 p-3 font-mono text-xs text-neutral-700">[#fmt var="record.details" type=text#]</div>
</details>
