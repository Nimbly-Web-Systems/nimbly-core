<div class="w-full rounded-md bg-neutral-50 px-3 py-2 shadow-md sm:px-4">
    <div class="hidden md:block">
        <table class="min-w-full">
            <thead>
                <tr>
                    <template x-for="column in [
                        { id: 'type', name: '[#text Type#]', class: 'whitespace-nowrap' },
                        { id: 'title', name: '[#text Title#]', class: '' },
                        { id: 'size', name: '[#text Size#]', class: 'whitespace-nowrap' },
                        { id: 'created', name: '[#text Created#]', class: 'whitespace-nowrap' },
                        { id: 'modified', name: '[#text Modified#]', class: 'whitespace-nowrap' },
                    ]" :key="column.id">
                        <th scope="col" class="border-b border-neutral-200 py-3 pr-4 text-left font-bold" :class="column.class"
                            :aria-sort="is_sorted_asc(column.id) ? 'ascending' : (is_sorted_desc(column.id) ? 'descending' : 'none')">
                            <div class="group flex cursor-pointer items-center gap-2" @click="toggle_sort(column.id)">
                                <span x-text="column.name"></span>
                                <span x-show="is_sorted_asc(column.id)" class="block">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                    </svg>
                                </span>
                                <span x-show="!is_sorted_asc(column.id)" class="block" :class="!is_sorted_desc(column.id) && 'invisible group-hover:visible'">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </span>
                            </div>
                        </th>
                    </template>
                    <th scope="col" class="border-b border-neutral-200 py-3 pr-4 text-left text-xs font-bold text-neutral-500">[#text Actions#]</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="doc in visible()" :key="doc.uuid">
                    <tr :draggable="can_drag()"
                        @dragstart="drag_start($event, doc)" @dragend="drag_end()"
                        @dragover.prevent.stop="drag_over_document($event, doc)"
                        @drop.prevent.stop="drop_on_document($event, doc)"
                        :class="{ 'opacity-40': dragging === doc.uuid, 'shadow-[inset_0_2px_0_var(--color-primary)]': drop_before === doc.uuid }">
                        <td class="border-b border-neutral-200 py-2 pr-4 text-neutral-600">
                            [#document-file-icon#]
                        </td>
                        <td class="w-full border-b border-neutral-200 py-2 pr-4 text-neutral-600">
                            <div class="w-0 min-w-full">
                                [#document-title#]
                            </div>
                        </td>
                        <td class="whitespace-nowrap border-b border-neutral-200 py-2 pr-4 text-neutral-600" x-text="format_size(doc.size)"></td>
                        <td class="whitespace-nowrap border-b border-neutral-200 py-2 pr-4 text-neutral-600" x-text="format_date(doc.created)"></td>
                        <td class="whitespace-nowrap border-b border-neutral-200 py-2 pr-4 text-neutral-600" x-text="format_date(doc.modified)"></td>
                        <td class="border-b border-neutral-200 py-2 pr-4 text-neutral-600">
                            [#document-actions#]
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <ul class="list md:hidden">
        <template x-for="doc in visible()" :key="doc.uuid">
            <li class="list-row items-center px-0"
                :draggable="can_drag()"
                @dragstart="drag_start($event, doc)" @dragend="drag_end()"
                @dragover.prevent.stop="drag_over_document($event, doc)"
                @drop.prevent.stop="drop_on_document($event, doc)"
                :class="{ 'opacity-40': dragging === doc.uuid, 'border-t-2 border-primary': drop_before === doc.uuid }">
                [#document-file-icon#]
                <div class="list-col-grow min-w-0">
                    [#document-title#]
                    <div class="text-xs text-neutral-500" x-text="format_size(doc.size)"></div>
                </div>
                [#document-actions#]
            </li>
        </template>
    </ul>
</div>
