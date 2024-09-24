Alpine.data("form_add", (resource_id = "(empty)") => ({
  resource_id: resource_id,
  busy: false,
  submit(e) {
    this.busy = true;
    nb.api
      .post(nb.base_url + "/api/v1/" + resource_id, {
        ...this.form_data,
        ...nb.edit.get_field_values(e.target),
      })
      .then((data) => {
        this.busy = false;
        if (data.success) {
          nb.system_message(nb.text.record_added).then((data) => {
            window.location.href = nb.base_url + "/nb-admin/" + resource_id;
          });
        } else {
          nb.notify(data.message);
        }
      });
  },
  ...nb.forms,
}));
