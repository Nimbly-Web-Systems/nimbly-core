document.addEventListener("alpine:init", () => {
    Alpine.data("site_settings", (name, description, side, languages = []) => {
        const source = description && typeof description === "object" ? description : {};
        const localized_description = {};

        languages.forEach((language, index) => {
            localized_description[language] = source[language]
                || (index === 0 && typeof description === "string" ? description : "");
        });

        return {
            busy: false,
            languages,
            active_language: languages[0] || "",
            form_data: {
                name,
                description: languages.length ? localized_description : description,
                nimblybar: { side },
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
