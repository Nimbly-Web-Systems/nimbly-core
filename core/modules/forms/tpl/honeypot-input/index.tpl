<input type="hidden" name="form_timestamp" value="[#date fmt=U#]">
<div class="form-control my-6" id="[#honeypot.field_name#]-field">
    <label for="[#honeypot.field_name#]" class="label">
        <span class="label-text">Company address</span>
    </label>
    <input type="text" value="" name="[#honeypot.field_name#]" placeholder="" required
        autocomplete="new-password"
        autocapitalize="off"
        autocorrect="off"
        spellcheck="false"
        tabindex="-1"
        x-init="form_data.[#honeypot.field_name#] = ''"
        x-model="form_data.[#honeypot.field_name#]"
        id="[#honeypot.field_name#]"
        class="input input-bordered w-full bg-neutral-50"
    />
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
