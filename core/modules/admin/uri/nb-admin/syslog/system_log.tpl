<tr>
    <td class="text-neutral-600 py-3 border-b border-neutral-200">
        [#fmt [#record.time#] type=date fmt="Y-m-d H:i"#]
    </td>
    [#set record_type="[#record.type#]" overwrite#]
    <td class="text-neutral-600 py-3 border-b border-neutral-200">
        <span class='border rounded-lg px-2 py-1
        [#if record_type="PHP Warning" echo="text-yellow-700 bg-yellow-100 border-yellow-700"#]
        [#if record_type="PHP Fatal error" echo="text-red-600 bg-red-100 border-red-600"#]
        [#if record_type="PHP Parse error" echo="text-neutral-50 bg-neutral-900 border-neutral-950"#]
        '>
            [#record.type#]
        </span>
    </td>
    <td class="text-neutral-600 py-3 border-b border-neutral-200">
        <details class="group max-w-3xl">
            <summary class="cursor-pointer list-none rounded px-1 py-0.5 hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-primary">
                [#fmt var="record.message" type=text max_length=96#]
                <span class="ml-1 text-xs text-neutral-400 group-open:hidden">[#text Show details#]</span>
                <span class="ml-1 hidden text-xs text-neutral-400 group-open:inline">[#text Hide details#]</span>
            </summary>
            <div class="mt-2 whitespace-pre-wrap break-all rounded bg-neutral-100 p-3 font-mono text-xs text-neutral-700">[#fmt var="record.message" type=text#]</div>
        </details>
    </td>
</tr>
