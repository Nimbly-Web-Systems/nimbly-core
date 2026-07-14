<section class="bg-neutral-100 pb-10">
    <form autocomplete="false" x-data="form_add('[#resource-id#]')" @submit.prevent="submit"
        class="rounded-md bg-neutral-50 p-3 shadow-md sm:p-4 md:p-6 lg:p-8 xl:p-10">
        [#set nb_form_edit=false overwrite#]
        [#form-key add_resource_[#resource-id#]#]
        <div class="max-w-lg">
            [#language-picker#]
            [#repeat data.fields var=_f#]

            <div class="mt-6 md:mt-8"></div>

            <button type="submit" class="[#btn-class-primary#] flex min-h-11 w-full flex-row items-center justify-center align-middle sm:w-auto" disabled="true"
                x-bind:disabled="busy">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="animate-spin w-5 h-5"
                    x-cloak x-show="busy">
                    <path opacity="0.2" fill-rule="evenodd" clip-rule="evenodd"
                        d="M12 19C15.866 19 19 15.866 19 12C19 8.13401 15.866 5 12 5C8.13401 5 5 8.13401 5 12C5 15.866 8.13401 19 12 19ZM12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                        fill="#ffffff" />
                    <path d="M2 12C2 6.47715 6.47715 2 12 2V5C8.13401 5 5 8.13401 5 12H2Z" fill="#ffffff" />
                </svg>
                <div class="text-sm font-bold px-2">[#text Save#]</div>
            </button>
        </div>
    </form>
</section>
