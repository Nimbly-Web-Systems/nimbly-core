<div class="relative my-6" data-te-input-wrapper-init>
    <input type="[item.type]" value="[get record.[item.key]]" name="[item.key]" placeholder="" 
        x-init="form_data.[item.key]='[get record.[item.key]]'"
        x-model="form_data.[item.key]"
        [if item.required=(not-empty) echo=required]
        class="
            peer block min-h-[auto] w-full rounded border-0 bg-transparent px-2 py-[0.2rem] 
            leading-[2.15] outline-none transition-all duration-200 ease-linear 
            focus:placeholder:opacity-100 
            motion-reduce:transition-none
            data-[te-input-state-active]:placeholder:opacity-100 
            [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
    />
    <label for="rewritebase" class="pointer-events-none absolute left-3 top-0 mb-0 
            max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[2.15] 
            text-neutral-600 transition-all duration-200 ease-out 
            peer-focus:-translate-y-[1.15rem] 
            peer-focus:scale-[0.8] peer-focus:text-primary 
            peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
            peer-data-[te-input-state-active]:scale-[0.8] 
            motion-reduce:transition-none">
            [text [field-name name="[item.name]"]]
    </label>
</div>
