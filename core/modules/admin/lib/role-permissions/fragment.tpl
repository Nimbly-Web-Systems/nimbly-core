<div class="space-y-7">
    [#_rp.section_special#]

    <div data-permission-extra class="space-y-7">
        [#_rp.section_resources#]
        [#_rp.section_system#]

        <section>
            <div class="mb-3">
                <h3 class="text-base font-semibold text-neutral-800">[#text Custom permissions#]</h3>
                <p class="mt-1 text-sm text-neutral-500">[#text For anything not covered above.#]</p>
            </div>
            <label for="custom_features" class="sr-only">[#text Custom permission tokens#]</label>
            <textarea id="custom_features" name="custom_features" rows="4"
                class="textarea textarea-bordered w-full bg-white font-mono text-sm">[#_rp.custom_features#]</textarea>
            <p class="mt-2 text-xs text-neutral-500">[#text One per line, or comma-separated.#]</p>
        </section>
    </div>
</div>
<script>
    [#include file=[#base-path#]core/modules/admin/lib/role-permissions/matrix-sync.js#]
</script>
