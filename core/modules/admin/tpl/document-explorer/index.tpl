[#init-document-explorer#]
<section class="min-h-screen bg-neutral-100 p-3 sm:p-4 md:p-6 lg:p-8 font-primary"
    x-data="document_explorer()"
    @dragenter.prevent="drag_enter($event)"
    @dragover.prevent="drag_over($event)"
    @dragleave="drag_leave($event)"
    @drop.prevent="drop_files($event)">

    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]
        <span aria-hidden="true">/</span>
        <span class="text-neutral-700">[#resource-name [#resource-id#] plural#]</span>
    </nav>
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex min-w-0 flex-wrap items-baseline gap-x-3 gap-y-1">
            <h1 class="min-w-0 break-words text-2xl font-semibold text-neutral-800 md:text-3xl"
                data-nb-edit-options='{"buttons":""}'>
                [#text [#resource-name [#resource-id#] plural#]#]
            </h1>
            <span class="text-sm text-neutral-500" data-nb-record-count>
                <span x-text="visible().length"></span>
                [#text of#]
                <span x-text="documents.length"></span>
                [#text records#]
            </span>
        </div>
        [#resource-actions#]
    </div>
    [#admin-resource-tabs panel=true#]

    <div class="mt-4 rounded-box border border-base-300 bg-base-100 p-2 sm:p-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <label class="input w-full sm:w-56 lg:w-64">
                <svg class="size-4 opacity-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input type="search" placeholder="[#text Search#]" x-model.debounce.150ms="search_term" />
            </label>
            <div class="flex items-center justify-end gap-2 sm:ml-auto">
                [#document-sort#]
                <div class="join" role="group" aria-label="[#text View#]">
                    <button type="button" class="btn btn-sm join-item" :class="layout === 'list' && 'btn-active'"
                        @click="set_layout('list')" title="[#text List#]" aria-label="[#text List#]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                    </button>
                    <button type="button" class="btn btn-sm join-item" :class="layout === 'grid' && 'btn-active'"
                        @click="set_layout('grid')" title="[#text Grid#]" aria-label="[#text Grid#]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                    </button>
                </div>
                <template x-if="config.can_create">
                    <label class="btn btn-sm btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        [#text Upload#]
                        <input type="file" multiple class="hidden" @change="upload_files($event.target.files, category); $event.target.value = ''" />
                    </label>
                </template>
            </div>
        </div>
    </div>

    <div class="mt-4 flex flex-col gap-4 md:flex-row md:items-start">
        <template x-if="has_categories()">
            <ul class="menu w-full rounded-box border border-base-300 bg-base-100 md:sticky md:top-4 md:w-56 md:shrink-0">
                <li>
                    <a href="#" @click.prevent="set_category('')" :class="category === '' && 'menu-active'"
                        @dragover.prevent="drop_target = '*'" @dragleave="drop_target = null"
                        @drop.prevent.stop="drop_on_category($event, '')">
                        <span class="grow">[#text All#]</span>
                        <span class="badge badge-sm badge-ghost" x-text="documents.length"></span>
                    </a>
                </li>
                <template x-for="value in config.category_order" :key="value">
                    <li>
                        <a href="#" class="group flex items-center gap-2" @click.prevent="set_category(value)"
                            :class="{ 'menu-active': category === value, 'ring-2 ring-primary': drop_target === value, 'opacity-40': dragging_category === value }"
                            :draggable="can_order_categories()"
                            @dragstart="category_drag_start($event, value)" @dragend="drag_end()"
                            @dragover.prevent="drop_target = value" @dragleave="drop_target = null"
                            @drop.prevent.stop="drop_on_category($event, value)"
                            @dblclick.prevent="start_rename(value); $nextTick(() => $refs.rename_input?.focus())"
                            x-show="renaming_category !== value">
                            <span class="grow truncate" x-text="config.categories[value]"></span>
                            <template x-if="config.can_edit_options && count_in(value) === 0">
                                <button type="button" class="text-neutral-400 opacity-0 hover:text-error group-hover:opacity-100 focus:opacity-100" title="[#text Delete#]" aria-label="[#text Delete#]"
                                    @click.prevent.stop="confirm('[#text Delete category?#]') && delete_category(value)">&times;</button>
                            </template>
                            <span class="badge badge-sm badge-ghost" x-text="count_in(value)"></span>
                        </a>
                        <template x-if="renaming_category === value">
                            <form class="px-1 py-1" @submit.prevent="rename_category()">
                                <input type="text" class="input input-sm w-full" x-model="category_label_draft" x-ref="rename_input"
                                    aria-label="[#text Rename#]" @blur="rename_category()"
                                    @keydown.escape="renaming_category = null" />
                            </form>
                        </template>
                    </li>
                </template>
                <template x-if="config.can_add_category">
                    <li>
                        <form x-show="adding_category" class="px-1 py-1" @submit.prevent="add_category()">
                            <input type="text" class="input input-sm w-full" x-model="new_category" x-ref="new_category"
                                placeholder="[#text Name#]" aria-label="[#text New category#]"
                                @keydown.escape="adding_category = false; new_category = ''" />
                        </form>
                        <button type="button" x-show="!adding_category" class="text-neutral-500"
                            @click="adding_category = true; $nextTick(() => $refs.new_category.focus())">
                            + [#text New category#]
                        </button>
                    </li>
                </template>
                <template x-if="config.can_manage_categories">
                    <li>
                        <a class="text-xs text-neutral-500" :href="`${nb.base_url}/nb-admin/${config.category_resource}`">[#text Manage categories#]</a>
                    </li>
                </template>
            </ul>
        </template>

        <div class="relative min-w-0 grow">
            <template x-if="uploads.length > 0">
                <ul class="mb-3 space-y-1 rounded-box border border-base-300 bg-base-100 p-3 text-sm">
                    <template x-for="upload in uploads" :key="upload.id">
                        <li class="flex items-center gap-3">
                            <span class="w-48 truncate" x-text="upload.name"></span>
                            <progress class="progress progress-primary grow" max="1" :value="upload.progress"></progress>
                        </li>
                    </template>
                </ul>
            </template>

            <div x-show="drag_files" x-cloak
                class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-box border-2 border-dashed border-primary bg-base-100/80 text-lg font-medium text-primary">
                <span x-text="category_label() ? '[#text Drop files to upload to#] ' + category_label() : '[#text Drop files to upload#]'"></span>
            </div>

            <template x-if="visible().length < 1">
                <div class="rounded-box border border-base-300 bg-base-100 p-10 text-center text-neutral-500">
                    <p x-text="search_term || category ? '[#text No matching records#]' : '[#text No records yet#]'"></p>
                    <template x-if="config.can_create">
                        <p class="mt-1 text-sm">[#text Drop files here to upload them.#]</p>
                    </template>
                </div>
            </template>

            <template x-if="visible().length > 0 && layout === 'list'">
                [#document-table#]
            </template>

            <template x-if="visible().length > 0 && layout === 'grid'">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                    <template x-for="doc in visible()" :key="doc.uuid">
                        <div class="group card card-sm border border-base-300 bg-base-100"
                            :draggable="can_drag()"
                            @dragstart="drag_start($event, doc)" @dragend="drag_end()"
                            @dragover.prevent.stop="drag_over_document($event, doc)"
                            @drop.prevent.stop="drop_on_document($event, doc)"
                            :class="{ 'opacity-40': dragging === doc.uuid, 'ring-2 ring-primary': drop_before === doc.uuid, 'cursor-grab': can_drag() }">
                            <a :href="view_url(doc)" class="relative flex aspect-[4/3] items-center justify-center overflow-hidden rounded-t-box bg-base-200">
                                <template x-if="has_preview(doc)">
                                    <img class="size-full" :class="is_page(doc) ? 'object-contain p-2 drop-shadow-sm' : 'object-cover'" :src="thumb_url(doc, 400, 300)" alt="" loading="lazy" draggable="false" @error="preview_failed(doc)" />
                                </template>
                                <template x-if="!has_preview(doc)">
                                    <div class="absolute inset-3 overflow-hidden rounded-sm bg-base-100 p-2.5 shadow-sm">
                                        <span class="line-clamp-4 break-words text-xs font-semibold leading-snug text-neutral-700" x-text="doc.title || doc.name"></span>
                                    </div>
                                </template>
                                <span class="absolute bottom-1.5 right-1.5">[#document-type-badge#]</span>
                            </a>
                            <div class="card-body gap-0.5">
                                <a class="line-clamp-2 break-words text-sm font-medium hover:underline" :href="view_url(doc)" :title="doc.title || doc.name" x-text="doc.title || doc.name"></a>
                                <div class="flex items-center justify-between gap-1 text-xs text-neutral-500">
                                    <span class="whitespace-nowrap" x-text="format_size(doc.size)"></span>
                                    <div class="flex opacity-100 transition md:opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100">
                                        [#document-actions#]
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</section>
