document.addEventListener("alpine:init", () => {
    Alpine.data("role_identity", (role_id, name, description) => ({
        role_id,
        busy: false,
        form_data: { name, description },
        submit() {
            this.busy = true;
            const features = nb_role_permissions_payload(this.$el);
            nb.api.put(nb.base_url + "/api/v1/roles/" + this.role_id, { ...this.form_data, features })
                .then((data) => {
                    this.busy = false;
                    if (data.success) {
                        nb.notify("Role saved");
                    } else {
                        nb.notify(data.message || "Could not save role");
                    }
                }).catch((err) => {
                    this.busy = false;
                    nb.notify(err.message || "Could not save role");
                });
        },
        delete_record() {
            nb.api.delete(nb.base_url + "/api/v1/roles/" + this.role_id)
                .then((data) => {
                    if (data.success) {
                        nb.system_message(nb.text.record_deleted);
                        window.location.href = nb.base_url + "/nb-admin/roles";
                    } else {
                        nb.notify(data.message || "Could not delete role");
                    }
                });
        },
    }));
});
