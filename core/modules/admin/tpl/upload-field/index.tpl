<div class="relative my-6">
	<input name="[#_fname#]"
		[#if item.required=(not-empty) echo=required#]
		class="relative m-0 block w-full min-w-0 flex-auto cursor-pointer rounded border 
			border-solid border-secondary-500 bg-transparent 
			bg-clip-padding px-3 py-2 text-base font-normal text-surface 
			[#get _inputbg default=bg-transparent#]
			transition duration-300 ease-in-out 
			file:-mx-3 file:-my-[0.32rem] file:me-3 file:cursor-pointer 
			file:overflow-hidden file:rounded-none file:border-0 file:border-e file:border-solid 
			file:border-inherit file:[#get _inputbg default=bg-transparent#] file:px-3  file:py-[0.32rem] file:text-surface 
			focus:border-primary focus:text-gray-700 
			focus:shadow-inset focus:outline-none 
			dark:border-white/70 dark:text-white  file:dark:text-white"
		type="file" id="[#_fname#]" 
		accept='[#get _faccept default=""#]' 
		data-nb-max-file-size="[#max-upload-size#]"
		/>

		<label class="pointer-events-none absolute left-3 top-0 mb-0 
		[#_fbg#]
		px-1
		max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[2.15] 
		text-neutral-600 transition-all duration-200 ease-out 
		-translate-y-[1.15rem] 
		scale-[0.8] 
		motion-reduce:transition-none">
			<div class="flex items-center">
				<div class="mr-1">
				[#_ftitle#]
				</div>
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20" stroke-width="1.5" stroke="currentColor" fill="currentColor" 
					class="w-4 h-6">
					<path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
				  </svg>
			</div>
		</label>
		<p class="text-xs pl-3 pt-1 text-neutral-500">
            [#text Max file size:#] [#fmt [#max-upload-size#] bytes#]
        </p>
</div>