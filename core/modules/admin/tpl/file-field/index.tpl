<div class="relative my-6 border border-neutral-300 rounded bg-neutral-50 p-4" data-nb-edit-image="[#_fname#]"
	x-init="[#_fmodel#]=`[#_fvalue#]`">

	[#if _fvalue=(not-empty) tpl=init_file_info#]
	
	<!-- file set -->
	<template x-if="[#_fmodel#]">
		
			<a :href="`[#base-url#]/download/${[#_fmodel#]}`" target="_blank"
				class=" flex flex-col items-center justify-center w-full h-[100px]">

				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.0"
					stroke="currentColor" class="w-8 h-8 ">
					<path stroke-linecap="round" stroke-linejoin="round"
						d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
				</svg>

				<div class="text-xs py-2 text-neutral-600">
					<p x-text="file_info.[#_fname#].name"></p>
				</div>
			</a>

	</template>

	<!-- no file set (empty) -->
	<template x-if="[#_fmodel#] == ''">
		<button class="flex flex-col items-center justify-center w-full h-[100px]" data-te-toggle="modal"
			data-te-target="#nb-modal-insert-media" @click.prevent="select_media('[#_fname#]')">
			<svg xmlns=" http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
				stroke="currentColor" class="w-4 h-4">
				<path stroke-linecap="round" stroke-linejoin="round"
					d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5">
				</path>
			</svg>
			<p>Click to upload file</p>
			<p class="text-xs text-neutral-500">
				[#text Max file size:#] [#fmt [#max-upload-size#] bytes#]
			</p>
		</button>
	</template>

	<button class="
        [#btn-class-icon#] absolute 
        right-3 top-1 p-1 text-neutral-600
        disabled:pointer-events-none disabled:text-neutral-200
         " :disabled="![#_fmodel#]" @click.prevent="delete_image('[#_fname#]')">
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
		[#_ftitle#]
	</label>
</div>