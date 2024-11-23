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
            if (document.referrer && !document.referrer.includes('/nb-admin/')) {
              window.location.href = document.referrer;
            } else {
              window.location.href = nb.base_url + '/nb-admin/' + resource_id;
            }
          });
        } else {
          nb.notify(data.message);
        }
      });
  },
  translate(lang) {
    this.busy = true;
    nb.api
      .post(nb.base_url + "/api/v1/openai/translate", {
        resource: this.resource_id,
        uuid: this.record_id,
        lang: lang,
      })
      .then((data) => {
        this.busy = false;
        if (data.success) {
          var uuid = Object.keys(data[this.resource_id])[0];
          nb.system_message(nb.text.record_added).then((data) => {
            window.location.href =
              nb.base_url + "/nb-admin/" + this.resource_id + "/" + uuid;
          });
        } else {
          nb.notify(data.message);
        }
      });
  },
  ...nb.forms,
}));
