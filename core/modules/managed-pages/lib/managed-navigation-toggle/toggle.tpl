<div class="mt-4 rounded-box border border-base-300 bg-base-100 p-3 sm:p-4"
    x-data="{
        enabled: [#_mn.enabled_json#],
        busy: false,
        toggle() {
            this.busy = true;
            nb.api.put(nb.base_url + '/api/v1/.config/managed_pages', { navigation_enabled: this.enabled })
                .then(data => { if (!data.success) throw new Error(data.message); location.reload(); })
                .catch(error => { this.enabled = !this.enabled; this.busy = false; nb.notify(error.message || 'Could not save settings'); });
        }
    }">
    <label class="flex cursor-pointer items-center justify-between gap-4">
        <span>
            <strong class="block text-sm">[#text Navigation editing#]</strong>
            <span class="text-sm text-neutral-500">[#text While on, editors can change the menus. Turn off to lock them as they are; visitors still see them.#]</span>
        </span>
        <input type="checkbox" class="toggle toggle-primary" x-model="enabled" @change="toggle()" :disabled="busy" aria-label="[#text Enable navigation editing#]">
    </label>
</div>
