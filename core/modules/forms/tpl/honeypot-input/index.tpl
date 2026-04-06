<input type="hidden" name="form_timestamp" value="[#date fmt=U#]">
<div class="relative my-6" data-te-input-wrapper-init id="[#honeypot.field_name#]-field">
    <input type="text" value="" name="[#honeypot.field_name#]" placeholder="" required 
        x-init="form_data.[#honeypot.field_name#] = ''"
        x-model="form_data.[#honeypot.field_name#]"
        class="
            peer block min-h-[auto] w-full rounded border-0 bg-transparent px-2 py-[0.2rem] 
            leading-[2.15] outline-none transition-all duration-200 ease-linear 
            focus:placeholder:opacity-100 
            motion-reduce:transition-none
            data-[te-input-state-active]:placeholder:opacity-100 
            [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
    />
    <label for="[#honeypot.field_name#]-field" class="pointer-events-none absolute left-3 top-0 mb-0 
            max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[2.15] 
            text-neutral-600 transition-all duration-200 ease-out 
            peer-focus:-translate-y-[1.15rem] 
            peer-focus:scale-[0.8] peer-focus:text-primary 
            peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
            peer-data-[te-input-state-active]:scale-[0.8] 
            motion-reduce:transition-none">
            Company address
    </label>
</div>

<script>
(function () {
    const el = document.getElementById('[#honeypot.field_name#]-field');
    if (el) {
        el.style.position = 'absolute';
        el.style.left = '-9999px';
        el.style.opacity = '0';

        window.setTimeout(function () {
            const input = el.querySelector('input');
            if (input) {
                input.removeAttribute('required');
            }
        }, 4000);
    }
})();
</script>