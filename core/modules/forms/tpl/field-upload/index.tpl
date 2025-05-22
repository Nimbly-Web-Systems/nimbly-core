<div class="relative my-10">
	<input type="file" name="[#_f.key#]" x-init="[#_f.model#]=null" x-ref="[#_f.key#]" 
		@change="[#_f.model#] = $refs.[#_f.key#].files[0]"
		accept='[#get _f.accept default=""#]' 
		[#if _f.required=(not-empty) echo=required#] class="
            block peer w-full rounded border-0 bg-transparent px-2 py-1 outline outline-1 outline-neutral-300
            leading-loose transition-all duration-75
            focus:outline-2 focus:outline-primary
			file:-mx-3 file:-my-[0.32rem] file:me-3 file:cursor-pointer 
			file:overflow-hidden file:rounded-none file:border-0 file:border-e file:border-solid 
			file:border-inherit file:bg-transparent file:px-3  file:py-[0.32rem] file:text-surface 
            " />
	<label for="[#_f.key#]" class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]
            text-neutral-800
            peer-focus:text-cdark">
		[#_f.title#]
		[#if _f.required=(not-empty) echo=" *"#]
	</label>
	<p class="text-xs pl-3 pt-1 text-neutral-500">
		[#text Format: TSV, UTF-8#]. [#text Max file size:#] [#fmt [#max-upload-size#] bytes#]
	</p>
</div>