document.addEventListener("alpine:init", () => {
    Alpine.data("site_settings", (name, description, side) => ({
        busy: false,
        form_data: { name, description, nimblybar: { side } },
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
    }));
});
