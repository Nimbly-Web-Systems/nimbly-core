Alpine.data("form_edit", (resource_id, record_id) => ({
  resource_id: resource_id,
  record_id: record_id,
  busy: false,
  submit(e) {
    this.busy = true;
    if (this.form_data.hasOwnProperty("keep_password")) {
      if (
        this.form_data.keep_password &&
        this.form_data.hasOwnProperty("password")
      ) {
        delete this.form_data.password;
      }
      delete this.form_data.keep_password;
    }
    nb.api
      .put(nb.base_url + "/api/v1/" + resource_id + "/" + record_id, {
        ...this.form_data,
        ...nb.edit.get_field_values(e.target),
      })
      .then((data) => {
        this.busy = false;
        if (data.success) {
          nb.system_message(nb.text.record_updated).then((data) => {
            history.back();
          });
        } else {
          nb.notify(data.message);
        }
      });
  },
  ...nb.forms,
}));
