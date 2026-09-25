document.addEventListener("alpine:init", () => {
    const json = value => JSON.stringify(value);
    const put = (url, payload) => nb.api.put(nb.base_url + url, payload).then(data => {
        if (!data.success) throw new Error(data.message || "Could not save settings");
    });

    Alpine.data("site_settings", (name, description, direction, side, languages, catalog, rtl, features) => {
        const codes = languages.map(language => language.code);
        // A plain-text name or description belongs to the default language. A plain-text
        // direction or side applies to every language; `choices` is its [ltr, rtl] default.
        const localize = (value, choices = null) => {
            const source = value && typeof value === "object" ? value : {};
            const plain = typeof value === "string" && value !== "" ? value : null;
            return Object.fromEntries(codes.map((code, index) => [
                code,
                source[code] ?? (choices ? plain ?? choices[rtl.includes(code) ? 1 : 0] : (index === 0 && plain) || ""),
            ]));
        };
        const site = {
            name: codes.length ? localize(name) : name,
            description: codes.length ? localize(description) : description,
            direction: codes.length ? localize(direction, ["ltr", "rtl"]) : (direction || "ltr"),
            nimblybar: { side: codes.length ? localize(side, ["left", "right"]) : (side || "left") },
        };
        const start = codes[0] || "";
        return {
            busy: false, languages, catalog, codes, site, features, new_language: "",
            active: { name: start, description: start, direction: start, side: start },
            original_site: json(site), original_features: json(features),

            init() {
                window.addEventListener("beforeunload", event => {
                    if (!this.dirty) return;
                    event.preventDefault();
                    event.returnValue = "";
                });
            },

            get site_dirty() { return json(this.site) !== this.original_site; },
            get features_dirty() { return json(this.features) !== this.original_features; },
            get dirty() { return this.site_dirty || this.features_dirty; },
            get available() {
                return Object.entries(this.catalog).filter(([code]) => !this.codes.includes(code)).map(([code, label]) => ({ code, label }));
            },

            // Each per-language field has its own language switch (`active`).
            target(field) { return field === "side" ? this.site.nimblybar : this.site; },
            value(field) {
                return this.codes.length ? this.target(field)[field][this.active[field]] : this.target(field)[field];
            },
            set_value(field, value) {
                if (this.codes.length) this.target(field)[field][this.active[field]] = value;
                else this.target(field)[field] = value;
            },

            // A language change (`languages`, the new order) saves right away, together with any other
            // unsaved edits; one save may add a language or reorder them, not both.
            submit(languages = null, message = "Settings saved") {
                this.busy = true;
                const was = JSON.parse(this.original_features);
                const switched = was.pages !== this.features.pages || was.navigation !== this.features.navigation;
                const site = languages || this.site_dirty
                    ? put("/api/v1/.config/site", languages ? { ...this.site, languages } : this.site)
                    : Promise.resolve();
                return site.then(() => {
                    this.original_site = json(this.site);
                    if (!this.features_dirty) return;
                    return put("/api/v1/.config/managed_pages", {
                        enabled: this.features.pages,
                        navigation_enabled: this.features.navigation,
                        enabled_page_types: this.features.page_types,
                    }).then(() => { this.original_features = json(this.features); });
                }).then(() => {
                    nb.notify(message);
                    // New languages and the Pages and Navigation tabs need a fresh page.
                    if (languages || switched) return location.reload();
                    this.busy = false;
                }).catch(error => {
                    this.busy = false;
                    nb.notify(error.message || "Could not save settings");
                });
            },
            // The first language is the default: put the chosen one first, keep the rest in order.
            make_default(code) {
                return this.submit([code, ...this.codes.filter(other => other !== code)], "Default language changed");
            },
            add_language() {
                const code = this.new_language;
                this.new_language = "";
                if (code) return this.submit([...this.codes, code], "Language added");
            },
        };
    });
});
