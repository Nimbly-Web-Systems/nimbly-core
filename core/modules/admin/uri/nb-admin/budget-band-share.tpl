<label class="ml-auto flex cursor-pointer items-center gap-2 text-sm font-normal text-neutral-600"
    x-data="{ share: [#_dash.budget_shared#], save() { nb.api.put(nb.base_url + '/api/v1/.config/budget', { share: this.share }).then(data => { if (!data.success) throw new Error(); }).catch(() => { this.share = !this.share; }); } }">
    <input type="checkbox" class="toggle toggle-primary toggle-sm" x-model="share" @change="save()">
    [#text Share with client#]
</label>
