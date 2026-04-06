<div class="form-control w-full">
    <label class="label">
        <span class="label-text">[#_f.title#][#if _f.required=(not-empty) echo=" *"#]</span>
    </label>
    <input type="password"
        class="input input-bordered w-full [#_f.bg#]"
        x-model="[#_f.model#]"
        [#if _f.required=(not-empty) echo=required#]
        placeholder="" />
</div>
