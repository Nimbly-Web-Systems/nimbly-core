<div class="block min-h-[1.5rem] pl-[1.5rem] mb-6">
    <input
        x-init="form_data.keep_password=true"
        x-model="form_data.keep_password"
        class="relative float-left -ml-[1.5rem] mr-[6px] mt-[0.15rem] h-[1.125rem] w-[1.125rem] 
            appearance-none rounded-[0.25rem] border-[0.125rem] border-solid border-neutral-300 
            outline-none before:pointer-events-none before:absolute before:h-[0.875rem] before:w-[0.875rem] 
            before:scale-0 before:rounded-full 
            before:bg-transparent before:opacity-0 before:shadow-[0px_0px_0px_13px_transparent] 
            before:content-[''] checked:border-primary checked:bg-primary checked:before:opacity-[0.16] checked:after:absolute checked:after:-mt-px checked:after:ml-[0.25rem] checked:after:block checked:after:h-[0.8125rem] checked:after:w-[0.375rem] checked:after:rotate-45 checked:after:border-[0.125rem] checked:after:border-l-0 checked:after:border-t-0 checked:after:border-solid checked:after:border-white checked:after:bg-transparent checked:after:content-[''] hover:cursor-pointer hover:before:opacity-[0.04] hover:before:shadow-[0px_0px_0px_13px_rgba(0,0,0,0.6)] focus:shadow-none focus:transition-[border-color_0.2s] focus:before:scale-100 focus:before:opacity-[0.12] focus:before:shadow-[0px_0px_0px_13px_rgba(0,0,0,0.6)] focus:before:transition-[box-shadow_0.2s,transform_0.2s] focus:after:absolute focus:after:z-[1] focus:after:block focus:after:h-[0.875rem] focus:after:w-[0.875rem] focus:after:rounded-[0.125rem] focus:after:content-[''] checked:focus:before:scale-100 checked:focus:before:shadow-[0px_0px_0px_13px_#3b71ca] checked:focus:before:transition-[box-shadow_0.2s,transform_0.2s] checked:focus:after:-mt-px checked:focus:after:ml-[0.25rem] checked:focus:after:h-[0.8125rem] checked:focus:after:w-[0.375rem] checked:focus:after:rotate-45 checked:focus:after:rounded-none checked:focus:after:border-[0.125rem] checked:focus:after:border-l-0 checked:focus:after:border-t-0 checked:focus:after:border-solid checked:focus:after:border-white checked:focus:after:bg-transparent dark:border-neutral-600 dark:checked:border-primary dark:checked:bg-primary dark:focus:before:shadow-[0px_0px_0px_13px_rgba(255,255,255,0.4)] dark:checked:focus:before:shadow-[0px_0px_0px_13px_#3b71ca]"
        type="checkbox" value="" id="cb_keep_password" checked />
    <label class="inline-block pl-[0.15rem] hover:cursor-pointer" for="cb_keep_password">
        [text Keep current password]
    </label>
</div>

<div class="relative border -mt-4 mb-8" data-te-input-wrapper-init x-cloak x-show="!form_data.keep_password">
    <input type="password" name="[item.key]" placeholder="" 
        x-model="form_data.[item.key]"
        :required="!form_data.keep_password"
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
            [text Create new password]
    </label>
</div>

