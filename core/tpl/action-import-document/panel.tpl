<section x-data="nb_import_document_action('[#get import_document_resource#]')" class="space-y-3 rounded-md"
    :class="over && 'outline outline-2 outline-offset-8 outline-primary'"
    @dragover.prevent="over = true" @dragleave="over = false"
    @drop.prevent="over = false; if (!busy) import_document($event.dataTransfer.files[0])">
    <h3 class="font-semibold text-neutral-800">[#text Import from document#]</h3>
    <p class="text-sm text-neutral-600">[#text Upload a document and AI will fill in the empty fields for you.#]</p>

    <input type="file" accept="[#get import_document_accept#]" x-ref="file" class="hidden"
        @change="import_document($event.target.files[0]); $event.target.value = '';">
    <button type="button" class="btn btn-sm w-full flex flex-row items-center justify-center gap-2"
        x-bind:disabled="busy" @click="$refs.file.click()">
        <svg x-cloak x-show="busy" viewBox="0 0 24 24" fill="none" class="h-4 w-4 animate-spin">
            <path opacity="0.2" fill-rule="evenodd" clip-rule="evenodd"
                d="M12 19C15.866 19 19 15.866 19 12C19 8.13401 15.866 5 12 5C8.13401 5 5 8.13401 5 12C5 15.866 8.13401 19 12 19ZM12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                fill="currentColor" />
            <path d="M2 12C2 6.47715 6.47715 2 12 2V5C8.13401 5 5 8.13401 5 12H2Z" fill="currentColor" />
        </svg>
        <span x-show="!busy" class="inline-flex">[#include file=[#base-path#]core/modules/forms/lib/field-actions/icon-sparkles.tpl#]</span>
        <span x-show="!busy">[#text Import from document#]</span>
        <span data-import-step x-show="shows($el)">[#text Reading the document…#]</span>
        <span data-import-step x-show="shows($el)">[#text Understanding the text…#]</span>
        <span data-import-step x-show="shows($el)">[#text Laying out the text…#]</span>
        [#if import_document_has_location=(not-empty) tpl=step-location#]
        [#if import_document_has_options=(not-empty) tpl=step-options#]
        <span data-import-step x-show="shows($el)">[#text Almost there, hold on…#]</span>
    </button>
    <p x-cloak x-show="busy" class="text-xs text-neutral-500">
        [#text This takes about a minute.#]
    </p>

    <p x-cloak x-show="attempted && !busy && filled.length > 0" class="text-xs text-neutral-600">
        [#text Filled in:#] <span x-text="filled.join(', ')"></span>
    </p>
    <p x-cloak x-show="attempted && !busy && filled.length === 0" class="text-xs text-neutral-600">
        [#text Nothing could be extracted from that document. Please fill in the fields manually.#]
    </p>
</section>
<script>
[#include file=[#base-path#]core/modules/admin/lib/import-document-action/action-import-document.js#]
</script>
