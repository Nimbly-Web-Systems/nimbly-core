<div class="relative my-6 border border-neutral-300 rounded bg-neutral-50 p-4" data-nb-edit-image="[#item.key#]"
    x-init="form_data.[#item.key#]='[#get record.[#item.key#]#]'">

    <!-- image set -->
    <template x-if="form_data.[#item.key#]">
        <figure class="flex items-center justify-center aspect-square w-[300px] h-[300px] mx-auto ">
            <img :src="`[#base-url#]/img/${form_data.[#item.key#]}/480x480f`" class="object-scale-down max-h-full">
        </figure>
    </template>

    <!-- no image set (empty) -->
    <template x-if="form_data.[#item.key#] == ''">
        <figure class="flex items-center justify-center aspect-square w-[300px] h-[300px] mx-auto bg-clight/10">
            <img src="[#empty-img#]" class="w-full h-full">
        </figure>
    </template>

    <button class="[#btn-class-icon#] absolute right-12 top-1 p-1 text-neutral-600" data-te-toggle="modal"
        data-te-target="#nb-modal-insert-media" @click.prevent="select_image('[#item.key#]')">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
        </svg>
    </button>

    <button class="
        [#btn-class-icon#] absolute 
        right-3 top-1 p-1 text-neutral-600
        disabled:pointer-events-none disabled:text-neutral-200
         " :disabled="!form_data.[#item.key#]" @click.prevent="delete_image('[#item.key#]')">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
        </svg>
    </button>

    <label for="" class="pointer-events-none absolute left-3 top-0 text-sm px-1
            text-neutral-600 
            -translate-y-[10px]
            bg-neutral-50">
        [#text [#field-name name="[#item.name#]"#]#]
    </label>
</div>