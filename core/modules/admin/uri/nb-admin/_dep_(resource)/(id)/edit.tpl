<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
    <div>
        <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">Edit [#resource-name [#data.resource#]#]</h1>
        <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[#text help_edit_resource#]</h3>
    </div>
</section>
<script>
    _frecord=[#fmt var=_frecord json#];
</script>
<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">
    <form autocomplete="false" x-ref="edit_resource_form" x-data="form_edit('[#data.resource#]', '[#data.uuid#]')" @submit.prevent="submit"
        class="bg-neutral-50 rounded-2xl p-2 sm:p-4 md:p-6 lg:p-8 xl:p-10 shadow-md">

        <div class="max-w-lg mx-auto">
            [#if has_translations=(not-empty) tpl=tabs-translations#]
            [#if show_language_tabs=(not-empty) tpl=tabs-languages#]

            [#set nb_form_edit=true overwrite#]
            [#form-key edit_resource_[#data.resource#]#]
            [#repeat data.fields#]
            [#set nb_form_edit=false overwrite#]
            <div class="mt-8"></div>

            <div class="flex flex-row items-center gap-4">

                <button type="submit" class="[#btn-class-primary#] flex flex-row align-middle" disabled="true"
                    @click="redirect_on_submit=true"
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
                <button type="button" class="[#btn-class-secondary#]" 
                    disabled="true" 
                    x-bind:disabled="false"
                    @click="confirm('[#text Delete record. Are you sure?#]') && delete_record">
                    [#text Delete#]
                </button>
            </div>
        </div>
    </form>
</section>
<script>
var _initial_lang="[#get record.lang default=en#]"
</script>