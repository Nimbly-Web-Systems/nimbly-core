<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
    <div>
        <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">Add [resource-name [data.resource]]</h1>
        <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[text help_create_resource]</h3>
    </div>
</section>

<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">
    <form autocomplete="false" x-data="form_add('[data.resource]')" 
        @submit.prevent="submit"
        class="bg-neutral-50 rounded-2xl p-10 shadow-md mx-auto">
        [set nb_form_edit=false overwrite]
        [form-key add_resource_[data.resource] x-init]
        <div class="max-w-lg mx-auto">
            [repeat data.fields]
        
        <div class="mt-8"></div>
        <input type="submit" value="Save" class="[btn-class-primary]" />
        </div>
        </div>
    </form>
</section>
