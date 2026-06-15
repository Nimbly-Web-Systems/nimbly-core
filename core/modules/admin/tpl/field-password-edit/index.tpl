<div class="[#_f.wrapper_class#]" x-init="form_data.keep_password = true">
    <label class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-white#]
            text-neutral-800">
        [#_f.title#]
    </label>
    <div class="border border-neutral-300 rounded-btn bg-white focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-neutral-400">
        <label class="flex items-center gap-3 cursor-pointer px-3 pt-4 pb-2">
            <input type="checkbox"
                class="checkbox checkbox-sm checkbox-primary"
                x-model="form_data.keep_password"
                @change="if (form_data.keep_password) form_data.[#_f.key#] = ''" />
            <span class="text-sm text-neutral-700">[#text Keep current password#]</span>
        </label>
        <div class="border-t border-neutral-300 px-3 py-1">
            <input type="password"
                class="input w-full border-0 focus:outline-none bg-transparent"
                x-model="[#_f.model#]"
                :disabled="form_data.keep_password"
                :placeholder="form_data.keep_password ? '••••••••••' : ''"
                :required="!form_data.keep_password" />
        </div>
    </div>
</div>
