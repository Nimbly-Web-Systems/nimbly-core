document.addEventListener("alpine:init", () => {
  Alpine.data("form_add", (resource_id = "(empty)") => ({
    resource_id: resource_id,
    lang: _initial_lang,
    busy: false,
    submit(e) {
      this.busy = true;
      const payload = {
        ...this.form_data,
        ...nb.edit.get_field_values(e.target),
      };
      // i18n fields are captured as flat scalars in one language at a time
      // (see language picker + render-field.php); wrap into the {lang: value}
      // shape the rest of the system expects only once, right before submit.
      (_i18n_fields || []).forEach((field) => {
        if (payload[field] !== undefined && typeof payload[field] !== "object") {
          payload[field] = { [this.lang]: payload[field] };
        }
      });
      nb.api
        .post(nb.base_url + "/api/v1/" + resource_id, payload)
        .then((data) => {
          this.busy = false;
          if (data.success) {
            nb.system_message(nb.text.record_added).then((data) => {
              window.location.href = _resource_url || (nb.base_url + "/nb-admin/" + resource_id);
            });
          } else {
            nb.notify(data.message);
          }
        });
    },
    ...nb.forms,
  }));
});
