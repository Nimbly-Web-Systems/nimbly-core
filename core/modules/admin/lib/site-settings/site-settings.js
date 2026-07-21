document.addEventListener("alpine:init", () => {
    Alpine.data("site_settings", (name, description, side, languages = []) => {
        const localize = (value, fallback = "") => {
            const source = value && typeof value === "object" ? value : {};
            const localized = {};

            languages.forEach((language) => {
                localized[language] = source[language] ?? (typeof value === "string" ? value : fallback);
            });

            return localized;
        };

        return {
            busy: false,
            languages,
            active_language: languages[0] || "",
            form_data: {
                name: languages.length ? localize(name) : name,
                description: languages.length ? localize(description) : description,
                nimblybar: { side: languages.length ? localize(side, "left") : side },
            },
            get current_name() {
                return this.languages.length ? this.form_data.name[this.active_language] : this.form_data.name;
            },
            get current_description() {
                return this.languages.length ? this.form_data.description[this.active_language] : this.form_data.description;
            },
            get current_side() {
                return this.languages.length ? this.form_data.nimblybar.side[this.active_language] : this.form_data.nimblybar.side;
            },
            set_current(field, value) {
                const target = field === "side" ? this.form_data.nimblybar : this.form_data;
                if (this.languages.length) {
                    target[field][this.active_language] = value;
                } else {
                    target[field] = value;
                }
            },
            submit() {
                this.busy = true;
                nb.api.put(nb.base_url + "/api/v1/.config/site", this.form_data)
                    .then((data) => {
                        this.busy = false;
                        if (data.success) {
                            nb.notify("Settings saved");
                        } else {
                            nb.notify(data.message || "Could not save settings");
                        }
                    }).catch((err) => {
                        this.busy = false;
                        nb.notify(err.message || "Could not save settings");
                    });
            },
        };
    });
});
