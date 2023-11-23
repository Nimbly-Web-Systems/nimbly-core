Alpine.data('form_edit', (resource_id, record_id) => ({
    resource_id: resource_id,
    record_id: record_id,
    form_data: {},
    submit() {
        if (this.form_data.hasOwnProperty('keep_password')) {
            if (this.form_data.keep_password && this.form_data.hasOwnProperty('password') ) {
                delete this.form_data.password;
            }
            delete this.form_data.keep_password;
        }
        nb.api.put(nb.base_url + '/api/v1/' + resource_id + '/' + record_id, this.form_data).then((data) => {
            if (data.success) {
                nb.system_message(nb.text.record_updated).then((data) => {
                    window.location.href = nb.base_url + '/nb-admin/' + resource_id;
                });
            } else {
                nb.notify(data.message);
            }
        })
    }
}));  