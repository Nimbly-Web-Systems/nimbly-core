<div class="mt-4 rounded-box border border-base-300 bg-base-100 p-3 sm:p-4"
    x-data="{
        enabled: [#_mp.enabled_json#],
        busy: false,
        toggle() {
            this.busy = true;
            nb.api.put(nb.base_url + '/api/v1/.config/managed_pages', { enabled: this.enabled })
                .then(data => { if (!data.success) throw new Error(data.message); location.reload(); })
                .catch(error => { this.enabled = !this.enabled; this.busy = false; nb.notify(error.message || 'Could not save settings'); });
        }
    }">
    <label class="flex cursor-pointer items-center justify-between gap-4">
        <span>
            <strong class="block text-sm">[#text Custom pages#]</strong>
            <span class="text-sm text-neutral-500">[#text While on, editors can add pages and edit the navigation. Turn off to take all custom pages offline; nothing is deleted.#]</span>
        </span>
        <input type="checkbox" class="toggle toggle-primary" x-model="enabled" @change="toggle()" :disabled="busy" aria-label="[#text Enable custom pages#]">
    </label>
</div>
