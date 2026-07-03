<form x-data="role_identity('[#_ri.role_id#]', [#_ri.name_json#], [#_ri.description_json#])" @submit.prevent="submit"
    class="mx-auto max-w-6xl overflow-hidden rounded-2xl bg-neutral-50 shadow-md">
    <div class="space-y-7 p-5">
        <div class="max-w-lg space-y-2">
            [#render-field def="roles.name" var="record.name"#]
            [#render-field def="roles.description" var="record.description"#]
        </div>

        [#role-permissions [#role-id#]#]

        <div class="flex flex-row items-center gap-4">
            <button type="submit" class="[#btn-class-primary#] flex flex-row items-center align-middle" x-bind:disabled="busy">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 animate-spin"
                    x-cloak x-show="busy">
                    <path opacity="0.2" fill-rule="evenodd" clip-rule="evenodd"
                        d="M12 19C15.866 19 19 15.866 19 12C19 8.13401 15.866 5 12 5C8.13401 5 5 8.13401 5 12C5 15.866 8.13401 19 12 19ZM12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                        fill="#ffffff" />
                    <path d="M2 12C2 6.47715 6.47715 2 12 2V5C8.13401 5 5 8.13401 5 12H2Z" fill="#ffffff" />
                </svg>
                <span class="px-1">[#text Save#]</span>
            </button>
            <button type="button" class="[#btn-class-secondary#]"
                @click="confirm('[#text Delete this role. Are you sure?#]') && delete_record()">
                [#text Delete role#]
            </button>
        </div>
    </div>

    <script>
        [#include file=[#base-path#]core/modules/admin/lib/role-identity/role-identity.js#]
    </script>
</form>
