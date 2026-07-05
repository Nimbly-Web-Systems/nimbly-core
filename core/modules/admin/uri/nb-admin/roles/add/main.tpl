<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]
        <span aria-hidden="true">/</span>
        <a class="hover:text-cnormal hover:underline" href="[#base-url#]/nb-admin/roles">[#text Roles#]</a>
        <span aria-hidden="true">/</span>
        <span class="text-neutral-700">[#text Add role#]</span>
    </nav>
    <h1 class="mb-6 text-2xl md:text-3xl font-semibold text-neutral-800">[#text Add role#]</h1>
</section>
<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 pt-0 font-primary">
    <form x-data="role_add()" @submit.prevent="submit"
        class="overflow-hidden rounded-2xl bg-neutral-50 shadow-md">
        <div class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-4 border-b border-neutral-200 bg-neutral-50/95 px-5 py-4 backdrop-blur">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-neutral-800">[#text New role#]</h2>
                <p class="mt-1 text-sm text-neutral-600">[#text Name it, then choose what it can see and change.#]</p>
            </div>
            <button type="submit" class="[#btn-class-primary#] flex flex-row items-center align-middle" x-bind:disabled="busy">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 animate-spin"
                    x-cloak x-show="busy">
                    <path opacity="0.2" fill-rule="evenodd" clip-rule="evenodd"
                        d="M12 19C15.866 19 19 15.866 19 12C19 8.13401 15.866 5 12 5C8.13401 5 5 8.13401 5 12C5 15.866 8.13401 19 12 19ZM12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                        fill="#ffffff" />
                    <path d="M2 12C2 6.47715 6.47715 2 12 2V5C8.13401 5 5 8.13401 5 12H2Z" fill="#ffffff" />
                </svg>
                <span class="px-1">[#text Create role#]</span>
            </button>
        </div>

        <div class="space-y-7 p-5">
            <div class="max-w-lg space-y-2">
                [#render-field def="roles.name" var="record.name"#]
                [#render-field def="roles.description" var="record.description"#]
            </div>

            [#role-permissions new#]
        </div>

        <script>
            [#include file=[#base-path#]core/modules/admin/uri/nb-admin/roles/add/role-add.js#]
        </script>
    </form>
</section>
