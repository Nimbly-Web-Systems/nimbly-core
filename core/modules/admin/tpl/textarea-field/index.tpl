<div class="relative mb-3 mt-6" data-te-input-wrapper-init [#get _fattr#]>
  [#if _fai=(not-empty) tpl=ai-btn#]
  <textarea
    class="peer block min-h-[auto] w-full rounded border-0 bg-transparent p-2 leading-[1.6] 
      outline-none transition-all duration-200 ease-linear 
      focus:placeholder:opacity-100 data-[te-input-state-active]:placeholder:opacity-100 
      motion-reduce:transition-none 
      [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
    name="[#_fname#]" id="[#_fname#]" placeholder="[#get item.placeholder default=#]"
    x-init="[#_fmodel#]=`[#_fvalue#]`" x-model="[#_fmodel#]" rows="[#get item.rows default=3#]">
      [#_fvalue#]
    </textarea>
  <label for="[#_fname#]" class="pointer-events-none absolute left-3 top-0 mb-0 z-10
    [#_fbg#]
    px-1
    max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[2.15] 
    text-neutral-600 transition-all duration-200 ease-out 
    -translate-y-[1.15rem] 
    scale-[0.8] 
    motion-reduce:transition-none">
    [#_ftitle#]
  </label>


</div>