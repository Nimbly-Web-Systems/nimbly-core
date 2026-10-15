<div x-data="{ on: [#_api.on#], busy: false, async save() { this.busy = true; const answer = await nb.api.put(nb.base_url + '/api/v1/users/[#_api.uuid#]', { api: { access: this.on } }); this.busy = false; if (answer.success) { nb.notify(nb.text.saved); } else { this.on = !this.on; nb.notify(answer.message); } } }">
    <label class="flex cursor-pointer items-center justify-between gap-3">
        <span class="text-sm font-semibold text-neutral-800">[#text API access#]</span>
        <input type="checkbox" class="toggle toggle-sm toggle-primary" x-model="on" @change="save()" :disabled="busy">
    </label>
</div>
