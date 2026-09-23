<tr class="border-b border-neutral-200" x-show="matches(records[[#_row.index#] - 1])">
    <td class="py-3 pr-4 text-neutral-600 [#_row.status_class#]">[#_row.status#]</td>
    <td class="py-3 pr-4 text-neutral-600">[#_row.type#]</td>
    <td class="py-3 pr-4 text-neutral-600 text-center">[#_row.attempts#]</td>
    <td class="py-3 pr-4 text-neutral-600">[#_row.last_error#]</td>
    <td class="py-3 pr-4 text-neutral-600">[#_row.updated#]</td>
    <td class="py-3 pr-4 text-right [#feature-cond manage-.jobs echo_else=hidden#]">
        <button type="button" [#_row.delete_disabled#] title="[#text Delete#]"
            @click="confirm('[#text Delete this job record?#]') && delete_job('[#_row.uuid#]', $el)"
            class="[#btn-class-icon#] flex h-11 w-11 items-center justify-center disabled:opacity-40 disabled:cursor-not-allowed md:h-auto md:w-auto">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
        </button>
    </td>
</tr>
