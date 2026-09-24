<section class="bg-neutral-100 p-3 sm:p-4 md:p-6 lg:p-8 font-primary">
    [#admin-page-header#]

    <p class="max-w-2xl text-sm text-neutral-600">[#text Use the arrow buttons to reorder links and to move them a level in or out.#]</p>

    <div class="mt-4" x-data='{ options: [#get navigation_editor_slots_json echo#], current: "[#navigation_editor_slot#]", language: "[#navigation_editor_language#]",
            go(slot) { location.href = "?slot=" + encodeURIComponent(slot) + "&language=" + encodeURIComponent(this.language); } }'
        x-show="options.length > 1" x-cloak>
        <div x-show="options.length <= 8" class="flex flex-wrap items-center gap-2" role="group" aria-label="[#text Navigation#]">
            <template x-for="option in options" :key="option.value">
                <a :href="'?slot=' + encodeURIComponent(option.value) + '&language=' + encodeURIComponent(language)"
                    class="inline-flex items-center rounded-full px-3 py-1.5 text-sm font-medium transition"
                    :class="option.value === current ? 'bg-cnormal text-white shadow-sm' : 'border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-100'"
                    :aria-current="option.value === current ? 'page' : null" x-text="option.label"></a>
            </template>
        </div>
        <select x-show="options.length > 8" class="select select-bordered select-sm" aria-label="[#text Navigation#]" x-model="current" @change="go(current)">
            <template x-for="option in options" :key="option.value"><option :value="option.value" x-text="option.label"></option></template>
        </select>
    </div>

    <nav class="mt-6 overflow-x-auto" aria-label="[#text Language#]"
        x-data='[#get navigation_editor_language_tabs_json echo#]' x-show="options.length > 1">
        <ul class="flex min-w-max flex-row" role="tablist">
            <template x-for="option in options" :key="option.value">
                <li><a role="tab" :aria-selected="option.value === current"
                    :href="'?slot=' + encodeURIComponent(slot) + '&language=' + encodeURIComponent(option.value)"
                    :class="option.value === current ? 'border-b-primary' : 'border-b-transparent'"
                    class="block cursor-pointer border-b-2 px-4 py-2 text-xs uppercase text-gray-600 hover:font-bold hover:text-black"
                    x-text="option.label"></a></li>
            </template>
        </ul>
    </nav>

    <div class="mt-6" x-data='navigation_editor([#get navigation_editor_config_json echo#])' id="navigation-editor"
        data-text-label="[#text Enter a label for this link.#]"
        data-text-target="[#text Choose a page or enter a valid destination.#]"
        data-text-depth="[#text This menu does not allow that many levels.#]"
        data-text-failed="[#text Navigation could not be saved.#]">
        <div x-show="stale" x-cloak class="alert alert-warning" role="alert">
            <span>[#text This navigation changed after you opened it. Reload to see the latest version; your unsaved edits on this page will be lost.#]</span>
            <a class="btn btn-sm" href="">[#text Reload#]</a>
        </div>
        <p x-show="failed" x-cloak class="alert alert-error" role="alert">[#text Navigation could not be saved.#]</p>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p x-show="!items.length" class="text-sm text-neutral-500">[#text No links yet.#]</p>
            <ol class="space-y-2">
                <template x-for="row in rows" :key="row.item.id">
                    <li :data-id="row.item.id" :style="{ marginLeft: (row.depth - 1) * 2 + 'rem' }"
                        class="rounded border bg-base-100 p-2" :class="errors[row.item.id] ? 'border-error' : 'border-base-300'">
                        <div class="flex flex-wrap items-center gap-2">
                            <input data-field="label" class="input input-bordered input-sm min-w-40 flex-1" x-model="row.item.label"
                                placeholder="[#text Label#]" aria-label="[#text Label#]">
                            <select class="select select-bordered select-sm" aria-label="[#text Destination type#]"
                                :value="row.item.target.kind" @change="set_kind(row.item, $event.target.value)">
                                <option value="page">[#text Page#]</option>
                                <option value="internal_url">[#text Internal URL#]</option>
                                <option value="external_url">[#text External URL#]</option>
                                <option value="group">[#text Group label#]</option>
                            </select>
                            <template x-if="row.item.target.kind === 'page'">
                                <select class="select select-bordered select-sm" aria-label="[#text Page#]"
                                    x-model="row.item.target.value" @change="page_chosen(row.item)"
                                    :class="page_missing(row.item) ? 'select-error' : ''">
                                    <option value="">[#text Choose a page#]</option>
                                    <template x-if="page_missing(row.item)">
                                        <option :value="row.item.target.value">[#text Missing page#]</option>
                                    </template>
                                    <template x-for="page in pages" :key="page.id">
                                        <option :value="page.id" x-text="page.title + (page.published ? '' : ' ([#text draft#])')"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="row.item.target.kind === 'internal_url' || row.item.target.kind === 'external_url'">
                                <input class="input input-bordered input-sm min-w-52" x-model="row.item.target.value" aria-label="[#text Destination#]"
                                    :placeholder="row.item.target.kind === 'external_url' ? 'https://…' : 'path/to/page'">
                            </template>
                            <button type="button" class="btn btn-square btn-ghost btn-sm" :disabled="row.index === 0" @click="move(row.item.id, 'up')" aria-label="[#text Move up#]">↑</button>
                            <button type="button" class="btn btn-square btn-ghost btn-sm" :disabled="row.index === row.count - 1" @click="move(row.item.id, 'down')" aria-label="[#text Move down#]">↓</button>
                            <button type="button" class="btn btn-square btn-ghost btn-sm" :disabled="!row.parent" @click="move(row.item.id, 'out')" aria-label="[#text Move one level out#]">←</button>
                            <button type="button" class="btn btn-square btn-ghost btn-sm" :disabled="!can_move_in(row)" @click="move(row.item.id, 'in')" aria-label="[#text Move under previous item#]">→</button>
                            <button type="button" class="btn btn-square btn-ghost btn-sm text-error" @click="move(row.item.id, 'delete')" aria-label="[#text Delete#]">×</button>
                        </div>
                        <p x-show="errors[row.item.id]" x-text="error_text(row.item.id)" class="mt-1 text-xs text-error" role="alert"></p>
                        <p x-show="page_draft(row.item)" class="mt-1 text-xs text-warning">[#text Hidden from the public menu until this page is published.#]</p>
                    </li>
                </template>
            </ol>
            <div class="mt-4 flex items-center gap-2">
                <button class="btn btn-sm" type="button" @click="add()">[#text Add link#]</button>
                <button class="btn btn-primary btn-sm" type="button" :disabled="busy || !dirty" @click="save()">[#text Save navigation#]</button>
                <span x-show="dirty" x-cloak class="text-xs text-neutral-500">[#text Unsaved changes#]</span>
            </div>
        </div>
    </div>
</section>
<script>[#include file=[#base-path#]core/modules/managed-pages/uri/nb-admin/navigation/navigation-editor.js#]</script>
