document.addEventListener("alpine:init", () => {
    const state = component => JSON.stringify({ enabled: component.enabled, pages_enabled: component.pages_enabled });

    Alpine.data("managed_pages_admin", (types, enabled, pages_enabled) => ({
        types, enabled, pages_enabled, busy: false, original: JSON.stringify({ enabled, pages_enabled }), was_enabled: pages_enabled,

        init() {
            window.addEventListener("beforeunload", event => {
                if (!this.dirty) return;
                event.preventDefault();
                event.returnValue = "";
            });
        },

        get dirty() {
            return state(this) !== this.original;
        },

        submit() {
            this.busy = true;
            return nb.api.put(nb.base_url + "/api/v1/.config/managed_pages", {
                enabled: this.pages_enabled,
                enabled_page_types: this.enabled,
            }).then(data => {
                if (!data.success) throw new Error(data.message);
                this.original = state(this);
                this.busy = false;
                // Tabs and menus depend on this switch, so show the new state.
                if (this.pages_enabled !== this.was_enabled) location.reload();
                else nb.notify(nb.text.saved);
            }).catch(error => {
                this.busy = false;
                nb.notify(error.message || "Could not save settings");
            });
        },
    }));
});
