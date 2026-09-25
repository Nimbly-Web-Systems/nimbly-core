document.addEventListener("alpine:init", () => {
    const json = value => JSON.stringify(value);
    const put = (url, payload) => nb.api.put(nb.base_url + url, payload).then(data => {
        if (!data.success) throw new Error(data.message || "Could not save settings");
    });

    Alpine.data("site_settings", (name, description, side, languages, catalog, features) => {
        const codes = languages.map(language => language.code);
        const localize = (value, fallback = "") => {
            const source = value && typeof value === "object" ? value : {};
            return Object.fromEntries(codes.map((code, index) => [
                code,
                source[code] ?? (typeof value === "string" && index === 0 ? value : fallback),
            ]));
        };
        const site = {
            name: codes.length ? localize(name) : name,
            description: codes.length ? localize(description) : description,
            nimblybar: { side: codes.length ? localize(side, "left") : side },
        };
        const start = codes[0] || "";
        return {
            busy: false, languages, catalog, codes, site, features, new_language: "",
            active: { name: start, description: start, side: start },
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

            submit() {
                this.busy = true;
                const was = JSON.parse(this.original_features);
                const switched = was.pages !== this.features.pages || was.navigation !== this.features.navigation;
                const site = this.site_dirty ? put("/api/v1/.config/site", this.site) : Promise.resolve();
                return site.then(() => {
                    this.original_site = json(this.site);
                    if (!this.features_dirty) return;
                    return put("/api/v1/.config/managed_pages", {
                        enabled: this.features.pages,
                        navigation_enabled: this.features.navigation,
                        enabled_page_types: this.features.page_types,
                    }).then(() => { this.original_features = json(this.features); });
                }).then(() => {
                    // The Pages and Navigation tabs follow these switches, so show the new tab bar.
                    if (switched) return location.reload();
                    this.busy = false;
                    nb.notify("Settings saved");
                }).catch(error => {
                    this.busy = false;
                    nb.notify(error.message || "Could not save settings");
                });
            },

            // Language changes save right away (a save may add a language or reorder them, not both),
            // so they wait until the other changes are saved.
            save_languages(order, message) {
                this.busy = true;
                return put("/api/v1/.config/site", { languages: order }).then(() => {
                    nb.notify(message);
                    location.reload();
                }).catch(error => {
                    this.busy = false;
                    nb.notify(error.message || "Could not save settings");
                });
            },
            // The first language is the default: put the chosen one first, keep the rest in order.
            make_default(code) {
                return this.save_languages([code, ...this.codes.filter(other => other !== code)], "Default language changed");
            },
            add_language() {
                const code = this.new_language;
                this.new_language = "";
                if (code) return this.save_languages([...this.codes, code], "Language added");
            },
        };
    });
});
