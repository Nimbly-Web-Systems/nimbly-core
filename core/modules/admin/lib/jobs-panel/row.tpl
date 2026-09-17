<tr class="border-b border-neutral-100 last:border-b-0">
    <td class="px-3 py-2 [#_row.status_class#]">[#_row.status#]</td>
    <td class="px-3 py-2">[#_row.type#]</td>
    <td class="px-3 py-2 text-center">[#_row.attempts#]</td>
    <td class="px-3 py-2 text-neutral-500">[#_row.last_error#]</td>
    <td class="px-3 py-2 text-neutral-500">[#_row.updated#]</td>
    <td class="px-3 py-2 text-right [#feature-cond manage-.jobs echo_else=hidden#]">
        <button type="button" [#_row.delete_disabled#]
            @click="confirm('[#text Delete this job record?#]') && delete_job('[#_row.uuid#]', $el)"
            class="text-red-600 hover:underline disabled:text-neutral-300 disabled:no-underline disabled:cursor-not-allowed">[#text Delete#]</button>
    </td>
</tr>
