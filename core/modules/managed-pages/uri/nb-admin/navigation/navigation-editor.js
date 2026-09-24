document.addEventListener("alpine:init", () => {
    const clone = value => JSON.parse(JSON.stringify(value));
    const uid = () => "item-" + Math.random().toString(36).slice(2, 10);
    const height = item => 1 + Math.max(0, ...(item.children || []).map(height));

    Alpine.data("navigation_editor", config => ({
        slot: config.slot,
        language: config.language,
        document_id: config.document_id,
        revision: config.revision,
        max_depth: config.max_depth,
        pages: config.pages,
        items: clone(config.items),
        original: JSON.stringify(config.items),
        errors: {},
        busy: false,
        stale: false,
        failed: false,
        texts: {},

        init() {
            this.texts = { ...this.$el.dataset };
            window.addEventListener("beforeunload", event => {
                if (!this.dirty) return;
                event.preventDefault();
                event.returnValue = "";
            });
        },

        get dirty() {
            return JSON.stringify(this.items) !== this.original;
        },

        // The tree flattened for rendering; Alpine templates cannot recurse.
        get rows() {
            const rows = [];
            const walk = (list, depth, parent) => list.forEach((item, index) => {
                rows.push({ item, depth, index, count: list.length, parent });
                walk(item.children || [], depth + 1, item);
            });
            walk(this.items, 1, null);
            return rows;
        },

        locate(id, list = this.items, parent = null) {
            for (let i = 0; i < list.length; i++) {
                if (list[i].id === id) return { list, i, item: list[i], parent };
                const found = this.locate(id, list[i].children || [], list[i]);
                if (found) return found;
            }
            return null;
        },

        can_move_in(row) {
            return row.index > 0 && row.depth + height(row.item) <= this.max_depth;
        },

        move(id, action) {
            const found = this.locate(id);
            if (!found) return;
            const { list, i, item, parent } = found;
            if (action === "up" && i > 0) [list[i - 1], list[i]] = [list[i], list[i - 1]];
            if (action === "down" && i < list.length - 1) [list[i + 1], list[i]] = [list[i], list[i + 1]];
            if (action === "in" && i > 0) {
                const row = this.rows.find(r => r.item.id === id);
                if (this.can_move_in(row)) {
                    list.splice(i, 1);
                    (list[i - 1].children ||= []).push(item);
                }
            }
            if (action === "out" && parent) {
                const up = this.locate(parent.id);
                list.splice(i, 1);
                up.list.splice(up.i + 1, 0, item);
            }
            if (action === "delete") list.splice(i, 1);
        },

        add() {
            const id = uid();
            this.items.push({
                id,
                label: "",
                target: { kind: this.pages.length ? "page" : "internal_url", value: "" },
                children: [],
            });
            this.$nextTick(() => document.querySelector(`[data-id="${id}"] [data-field="label"]`)?.focus());
        },

        set_kind(item, kind) {
            item.target = { kind, value: "" };
            delete this.errors[item.id];
        },

        // Picking a page for a link without a label uses the page title.
        page_chosen(item) {
            delete this.errors[item.id];
            const page = this.pages.find(p => p.id === item.target.value);
            if (page && !item.label.trim()) item.label = page.title;
        },

        page_of(item) {
            return item.target.kind === "page" ? this.pages.find(p => p.id === item.target.value) : null;
        },

        page_missing(item) {
            return item.target.kind === "page" && item.target.value !== "" && !this.page_of(item);
        },

        page_draft(item) {
            return !!this.page_of(item) && !this.page_of(item).published;
        },

        error_text(id) {
            const reason = this.errors[id];
            if (!reason) return "";
            return {
                label: this.texts.textLabel,
                target: this.texts.textTarget,
                depth: this.texts.textDepth,
            }[reason] || this.texts.textFailed;
        },

        check() {
            const errors = {};
            const walk = list => list.forEach(item => {
                const kind = item.target.kind;
                const value = (item.target.value || "").trim();
                if (!item.label.trim()) errors[item.id] = "label";
                else if (kind !== "group" && value === "") errors[item.id] = "target";
                else if (kind === "external_url" && !/^https?:\/\//i.test(value)) errors[item.id] = "target";
                walk(item.children || []);
            });
            walk(this.items);
            return errors;
        },

        save() {
            this.failed = false;
            this.errors = this.check();
            if (Object.keys(this.errors).length) {
                nb.notify(this.texts.textFailed);
                return;
            }
            this.busy = true;
            const fail = data => {
                this.busy = false;
                const detail = String((data && data.detail) || "");
                const item_error = detail.match(/^items\.([^:]+):(.+)$/);
                if (detail === "revision:stale") this.stale = true;
                else if (item_error) this.errors = { [item_error[1]]: item_error[2] };
                else this.failed = true;
                nb.notify(this.texts.textFailed);
                return false;
            };
            return nb.api.put(nb.base_url + "/api/v1/.navigation/" + this.document_id, {
                slot: this.slot,
                language: this.language,
                items: this.items,
                revision: this.revision,
            }).then(data => {
                if (!data.success) return fail(data);
                const saved = data[".navigation"][this.document_id];
                this.items = clone(saved.items);
                this.original = JSON.stringify(saved.items);
                this.revision = saved._revision;
                this.busy = false;
                nb.notify(nb.text.saved);
                return true;
            }).catch(error => fail(error && (error.data || error)));
        },
    }));
});
