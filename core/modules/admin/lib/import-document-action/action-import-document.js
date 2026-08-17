// Plain JS, no shortcode syntax — raw-included via [#include#] so it stays
// pure JS (see edit-form-state.js for why that split matters here too).
// Self-contained: this component owns its own upload/busy/result state and
// never reaches into the add form's Alpine scope directly (it's rendered as
// a sibling <aside>, outside the form's x-data tree) — it reads the form's
// current field values from the DOM to send as context, then hands its
// result to the form via a single dispatched event the form applies itself.
document.addEventListener("alpine:init", () => {
  Alpine.data("nb_import_document_action", (resource) => ({
    busy: false,
    attempted: false,
    filled: [],

    import_document(file) {
      if (!file) {
        return;
      }
      this.busy = true;
      this.attempted = false;
      this.filled = [];

      const form_el = document.querySelector("form[x-data]");
      const current_values = {};
      if (form_el) {
        form_el.querySelectorAll("[name]").forEach((el) => {
          if (el.name && el.type !== "file" && el.type !== "submit" && el.type !== "hidden") {
            current_values[el.name] = el.value;
          }
        });
      }

      const data = new FormData();
      data.append("file", file);
      data.append("current_values", JSON.stringify(current_values));

      fetch(nb.base_url + "/api/v1/" + resource + "/import-document", {
        method: "POST",
        body: data,
      })
        .then((res) => res.json())
        .then((result) => {
          this.busy = false;
          this.attempted = true;
          if (!result.success) {
            nb.notify(result.message || "Could not import document");
            return;
          }
          this.filled = Object.keys(result.values || {});
          if (form_el) {
            form_el.dispatchEvent(
              new CustomEvent("nb:import-document-result", { detail: result })
            );
          }
        })
        .catch((err) => {
          this.busy = false;
          this.attempted = true;
          nb.notify(err.message || "Could not import document");
        });
    },
  }));
});
