document.addEventListener("alpine:init", () => {
    Alpine.data("role_add", () => ({
        busy: false,
        form_data: { name: "", description: "" },
        submit() {
            this.busy = true;
            const features = nb_role_permissions_payload(this.$el);
            nb.api.post(nb.base_url + "/api/v1/roles", { ...this.form_data, features }).then((data) => {
                this.busy = false;
                if (!data.success) {
                    nb.notify(data.message || "Could not create role");
                    return;
                }

                const role_id = Object.keys(data.roles)[0];
                nb.system_message(nb.text.record_added).then(() => {
                    window.location.href = nb.base_url + "/nb-admin/roles/" + role_id;
                });
            }).catch((err) => {
                this.busy = false;
                nb.notify(err.message || "Could not create role");
            });
        },
    }));
});
