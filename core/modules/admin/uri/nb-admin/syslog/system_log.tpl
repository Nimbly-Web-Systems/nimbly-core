<tr>
    <td class="text-neutral-600 py-3 border-b border-neutral-200">
        [#fmt [#record.time#] type=date fmt="Y-m-d H:i"#]
    </td>
    [#set record_type="[#record.type#]" overwrite#]
    [#if record_type="PHP Warning" echo="text-orange-600"#]
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
        [#fmt var="record.message" type=text max_length=64#]
    </td>
</tr>
