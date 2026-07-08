<script type="application/json" id="nb-group-fields-[#_f.key#]">[#fmt var=_f.fields type=json empty={}#]</script>
<script type="application/json" id="nb-group-value-[#_f.key#]">[#fmt var=_f.value type=json empty=[]#]</script>

<script>
if (!window.nb_group_field) {
    window.nb_group_field = function(field_key, max_items) {
        return {
            key: field_key,
            max: Number(max_items || 99),
            fields: {},
            items: [],
            init() {
                this.fields = this.json_from(`nb-group-fields-${this.key}`, {});
                const raw = this.json_from(`nb-group-value-${this.key}`, []);
                this.items = Array.isArray(raw) ? raw.map(item => this.normalize(item)) : [];
                if (this.items.length === 0) {
                    this.add();
                }
                form_data[this.key] = this.items;
            },
            json_from(id, fallback) {
                try {
                    return JSON.parse(document.getElementById(id)?.textContent || JSON.stringify(fallback));
                } catch (e) {
                    return fallback;
                }
            },
            field_entries() {
                return Object.entries(this.fields || {}).map(([key, field]) => ({ key, field }));
            },
            normalize(item = {}) {
                const entry = {};
                this.field_entries().forEach(({ key, field }) => {
                    if (Object.prototype.hasOwnProperty.call(item, key)) {
                        entry[key] = item[key];
                        return;
                    }
                    if (field.multi) {
                        entry[key] = [];
                    } else if (field.type === "boolean") {
                        entry[key] = false;
                    } else {
                        entry[key] = field.default ?? "";
                    }
                });
                return entry;
            },
            add() {
                if (this.items.length >= this.max) return;
                this.items.push(this.normalize());
                form_data[this.key] = this.items;
            },
            remove(ix) {
                if (this.items.length === 1) {
                    this.items.splice(0, 1, this.normalize());
                    return;
                }
                this.items.splice(ix, 1);
            },
            move(ix, delta) {
                const target = ix + delta;
                if (target < 0 || target >= this.items.length) return;
                this.items.splice(target, 0, this.items.splice(ix, 1)[0]);
            },
            option_entries(field) {
                return Object.entries(field.options || {}).map(([value, label]) => ({ value, label }));
            },
            toggle_multi(entry, key, value, checked) {
                if (!Array.isArray(entry[key])) entry[key] = [];
                entry[key] = checked
                    ? [...new Set([...entry[key], value])]
                    : entry[key].filter(item => item !== value);
            },
            input_type(field) {
                if (["date", "email", "number", "url"].includes(field.type)) {
                    return field.type;
                }
                return "text";
            }
        };
    };
}
</script>

<div class="my-6 rounded border border-neutral-300 bg-neutral-50 p-3"
    x-data="nb_group_field('[#_f.key#]', [#get _f.max default=99#])">
    <div class="mb-3 flex items-center justify-between gap-3">
        <h4 class="text-sm font-bold text-neutral-800">
            [#_f.title#][#if _f.required=(not-empty) echo=" *"#]
        </h4>
        <button type="button" class="btn btn-sm btn-outline" @click.prevent="add" :disabled="items.length >= max">
            [#text Add#]
        </button>
    </div>

    <template x-for="(entry, ix) in items" :key="ix">
        <div class="mb-3 rounded border border-neutral-200 bg-white">
            <div class="flex items-center justify-between border-b border-neutral-200 bg-neutral-100 px-3 py-2">
                <span class="text-xs font-bold text-neutral-500" x-text="ix + 1"></span>
                <div class="flex items-center gap-1">
                    <button type="button" class="btn btn-xs btn-ghost" @click.prevent="move(ix, -1)" :disabled="ix === 0" title="[#text Move up#]">&uarr;</button>
                    <button type="button" class="btn btn-xs btn-ghost" @click.prevent="move(ix, 1)" :disabled="ix === items.length - 1" title="[#text Move down#]">&darr;</button>
                    <button type="button" class="btn btn-xs btn-ghost" @click.prevent="remove(ix)" title="[#text Delete#]">&times;</button>
                </div>
            </div>
            <div class="grid gap-4 p-4 md:grid-cols-2">
                <template x-for="field_entry in field_entries()" :key="field_entry.key">
                    <div class="relative" :class="field_entry.field.type === 'textarea' ? 'md:col-span-2' : ''">
                        <template x-if="field_entry.field.type === 'textarea'">
                            <textarea class="textarea textarea-bordered w-full" rows="2"
                                x-model="entry[field_entry.key]"></textarea>
                        </template>
                        <template x-if="field_entry.field.type === 'select' && field_entry.field.multi">
                            <div class="rounded border border-neutral-300 bg-white px-3 py-2">
                                <div class="flex flex-wrap gap-3 pt-1">
                                    <template x-for="option in option_entries(field_entry.field)" :key="option.value">
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" class="checkbox checkbox-sm" :value="option.value"
                                                :checked="Array.isArray(entry[field_entry.key]) && entry[field_entry.key].includes(option.value)"
                                                @change="toggle_multi(entry, field_entry.key, option.value, $event.target.checked)" />
                                            <span x-text="option.label"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <template x-if="field_entry.field.type === 'select' && !field_entry.field.multi">
                            <select class="select select-bordered w-full" x-model="entry[field_entry.key]">
                                <option value=""></option>
                                <template x-for="option in option_entries(field_entry.field)" :key="option.value">
                                    <option :value="option.value" x-text="option.label"></option>
                                </template>
                            </select>
                        </template>
                        <template x-if="field_entry.field.type === 'boolean'">
                            <label class="flex min-h-12 items-center gap-3 rounded border border-neutral-300 bg-white px-3">
                                <input type="checkbox" class="checkbox checkbox-sm" x-model="entry[field_entry.key]" />
                                <span class="text-sm font-bold text-neutral-800" x-text="field_entry.field.name || field_entry.key"></span>
                            </label>
                        </template>
                        <template x-if="field_entry.field.type === 'number'">
                            <input class="input input-bordered w-full" type="number"
                                x-model.number="entry[field_entry.key]" />
                        </template>
                        <template x-if="!['textarea', 'select', 'boolean', 'number'].includes(field_entry.field.type)">
                            <input class="input input-bordered w-full" :type="input_type(field_entry.field)"
                                x-model="entry[field_entry.key]" />
                        </template>
                        <label x-show="field_entry.field.type !== 'boolean'"
                            class="pointer-events-none absolute left-3 -top-2.5 bg-white px-1 text-sm font-bold leading-tight text-neutral-800"
                            x-text="field_entry.field.name || field_entry.key"></label>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
