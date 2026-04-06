<div class="form-control w-full" x-init="form_data.keep_password = true">

    <label class="label">
        <span class="label-text">[#_f.title#]</span>
    </label>

    <label class="label cursor-pointer justify-start gap-3 pt-0 pb-2">
        <input type="checkbox"
            class="checkbox checkbox-sm checkbox-primary"
            x-model="form_data.keep_password"
            @change="if (form_data.keep_password) form_data.[#_f.key#] = ''" />
        <span class="label-text">[#text Keep current password#]</span>
    </label>

    <input type="password"
        class="input input-bordered w-full [#_f.bg#]"
        x-model="[#_f.model#]"
        :disabled="form_data.keep_password"
        :placeholder="form_data.keep_password ? '••••••••••' : ''"
        :required="!form_data.keep_password"
        placeholder="" />

</div>
