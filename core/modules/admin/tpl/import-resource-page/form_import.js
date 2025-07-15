document.addEventListener("alpine:init", () => {
  Alpine.data("form_import", (resource_id = "(empty)") => ({
    resource_id: resource_id,
    busy: false,
    submit() {
      this.busy = true;
      const formData = new FormData();
      for (const [key, value] of Object.entries(this.form_data)) {
        if (value instanceof File) {
          formData.append(key, value);
        }
      }

      fetch(nb.base_url + "/api/v1/" + this.resource_id + "/import", {
        method: "POST",
        body: formData,
      })
        .then((res) => res.json())
        .then((data) => {
          this.busy = false;
          if (data.success) {
            nb.system_message(
              `
              <div class="text-sm leading-tight">
                <b>Imported: ${data.stats.imported}/${data.stats.total}</b><br />
                (${data.stats.created} created, ${data.stats.updated} updated, ${data.stats.errors} errors)
              </div>`
            ).then(() => {
              window.location.href =
                _resource_url || nb.base_url + "/nb-admin/" + this.resource_id;
            });
          } else {
            nb.notify(data.message);
          }
        })
        .catch((err) => {
          this.busy = false;
          nb.notify(err.message || "Upload failed");
        });
    },
    ...nb.forms,
  }));
});
