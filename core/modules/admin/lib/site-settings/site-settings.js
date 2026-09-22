document.addEventListener("alpine:init", () => {
    const json = value => JSON.stringify(value);
    const warn_if_dirty = component => window.addEventListener("beforeunload", event => {
        if (!component.dirty) return;
        event.preventDefault();
        event.returnValue = "";
    });
    const save = (url, payload, component, success_message) => {
        component.busy = true;
        component.saved = false;
        return nb.api.put(nb.base_url + url, payload).then(data => {
            component.busy = false;
            if (!data.success) {
                nb.notify(data.message || "Could not save settings");
                return false;
            }
            component.saved = true;
            component.original = json(payload);
            nb.notify(success_message);
            return true;
        }).catch(error => {
            component.busy = false;
            nb.notify(error.message || "Could not save settings");
            return false;
        });
    };

    Alpine.data("site_settings_general", (name, description, side, languages = []) => {
        const localize = (value, fallback = "") => {
            const source = value && typeof value === "object" ? value : {};
            return Object.fromEntries(languages.map((language, index) => [
                language,
                source[language] ?? (typeof value === "string" && index === 0 ? value : fallback),
            ]));
        };
        const form_data = {
            name: languages.length ? localize(name) : name,
            description: languages.length ? localize(description) : description,
            nimblybar: { side: languages.length ? localize(side, "left") : side },
        };
        return {
            busy: false, saved: false, languages, active_language: languages[0] || "", form_data,
            original: json(form_data),
            init() { warn_if_dirty(this); },
            get dirty() { return json(this.form_data) !== this.original; },
            get current_name() { return this.languages.length ? this.form_data.name[this.active_language] : this.form_data.name; },
            get current_description() { return this.languages.length ? this.form_data.description[this.active_language] : this.form_data.description; },
            get current_side() { return this.languages.length ? this.form_data.nimblybar.side[this.active_language] : this.form_data.nimblybar.side; },
            set_current(field, value) {
                this.saved = false;
                const target = field === "side" ? this.form_data.nimblybar : this.form_data;
                if (this.languages.length) target[field][this.active_language] = value;
                else target[field] = value;
            },
            submit() { return save("/api/v1/.config/site", this.form_data, this, "Settings saved"); },
        };
    });

    Alpine.data("site_settings_languages", (configured, catalog) => ({
        busy: false, saved: false, configured, catalog, new_language: "", original: json(configured),
        init() { warn_if_dirty(this); },
        get dirty() { return this.new_language !== ""; },
        get available() {
            const used = new Set(this.configured.map(language => language.code));
            return Object.entries(this.catalog).filter(([code]) => !used.has(code)).map(([code, label]) => ({ code, label }));
        },
        submit() {
            if (!this.new_language) return;
            const code = this.new_language;
            const payload = { languages: [...this.configured.map(language => language.code), code] };
            return save("/api/v1/.config/site", payload, this, "Language added").then(ok => {
                if (!ok) return;
                this.configured.push({ code, label: this.catalog[code] || code.toUpperCase(), fallback: false });
                this.new_language = "";
                this.original = json(this.configured);
            });
        },
    }));

    Alpine.data("site_settings_page_types", (types, enabled) => ({
        busy: false, saved: false, types, enabled, original: json(enabled),
        init() { warn_if_dirty(this); },
        get dirty() { return json(this.enabled) !== this.original; },
        submit() {
            return save("/api/v1/.config/managed_pages", { enabled_page_types: this.enabled }, this, "Page templates saved")
                .then(ok => { if (ok) this.original = json(this.enabled); });
        },
    }));
});
