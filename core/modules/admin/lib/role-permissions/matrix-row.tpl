<tr class="border-b border-neutral-100 last:border-b-0" data-permission-row="[#_row.resource#]"
    x-show="!search || '[#_row.resource#] [#_row.label#]'.toLowerCase().includes(search.toLowerCase())">
    <th scope="row" class="sticky left-0 z-10 bg-white px-3 py-2.5 text-left font-medium text-neutral-800">
        [#_row.label#][#_row.core_badge#]
    </th>
    <td class="bg-amber-50/60 px-3 py-2 text-center">[#_row.manage_checkbox#]</td>
    [#_row.operation_cells#]
</tr>
