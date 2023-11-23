Alpine.data('form_add', (resource_id="(empty)") => ({
    resource_id: resource_id,
    form_data: {},
    submit() {
        nb.api.post(nb.base_url + '/api/v1/' + resource_id, this.form_data).then((data) => {
            if (data.success) {
                nb.system_message(nb.text.record_added).then((data) => {
                    window.location.href = nb.base_url + '/nb-admin/' + resource_id;
                });
            } else {
                nb.notify(data.message);
            }
        })
    }
}));  