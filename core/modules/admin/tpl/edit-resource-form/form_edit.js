document.addEventListener("alpine:init", () => {
  Alpine.data("form_edit", (resource_id, record_id) => ({
    resource_id: resource_id,
    record_id: record_id,
    lang: _initial_lang,
    redirect_on_submit: true,
    busy: false,
    ai_busy_field: null,
    ai_busy_all: false,
    ai_all_disabled: true,

    init() {
      this.form_data = window._frecord || {};

      this.$watch("lang", (lang) => {
        this.set_editors(lang);
        this.$nextTick(() => this.refresh_ai_actions(lang));
      });

      this.$nextTick(() => {
        this.set_editors(this.lang);
        this.refresh_ai_actions(this.lang);
      });
    },

    set_editors(lang) {
      if (!this.$refs.edit_resource_form) {
        return;
      }

      const editors =
        this.$refs.edit_resource_form.querySelectorAll("[data-nb-edit]");

      editors.forEach((el) => {
        const parts = el.dataset.nbEdit.split(".");
        const field = parts[parts.length - 1];
        const field_data = this.form_data[field];

        if (!field_data || typeof field_data !== "object") {
          return;
        }

        const value = field_data[lang] || "";

        if (el._nb_medium_editor) {
          el._nb_medium_editor.destroy();
          delete el._nb_medium_editor;
        }

        el.innerHTML = value;
        nb.edit.init_editor(el, true);
      });
    },

    sync_editors(lang) {
      if (!this.$refs.edit_resource_form) {
        return;
      }

      const editors =
        this.$refs.edit_resource_form.querySelectorAll("[data-nb-edit]");

      editors.forEach((el) => {
        const parts = el.dataset.nbEdit.split(".");
        const field = parts[parts.length - 1];
        const field_data = this.form_data[field];

        if (!field_data || typeof field_data !== "object") {
          return;
        }

        this.form_data[field][lang] = el.innerHTML.trim();
      });
    },

    get_editor_values() {
      const editor_values = nb.edit.get_field_values(this.$refs.edit_resource_form);

      Object.keys(editor_values).forEach((field) => {
        const field_data = this.form_data[field];

        if (field_data && typeof field_data === "object" && typeof editor_values[field] !== "object") {
          delete editor_values[field];
        }
      });

      return editor_values;
    },

    submit() {
      this.busy = true;
      this.sync_editors(this.lang);
      if (this.form_data.hasOwnProperty("keep_password")) {
        if (
          this.form_data.keep_password &&
          this.form_data.hasOwnProperty("password")
        ) {
          delete this.form_data.password;
        }
        delete this.form_data.keep_password;
      }
      const payload = {
        ...this.form_data,
        ...this.get_editor_values(),
      };
      if (typeof _translation_mode !== "undefined" && _translation_mode === "field") {
        delete payload.lang;
        delete payload.translations;
      }
      nb.api
        .put(nb.base_url + "/api/v1/" + resource_id + "/" + record_id, payload)
        .then((data) => {
          this.busy = false;
          if (data.success) {
            if (this.redirect_on_submit) {
              nb.system_message(nb.text.record_updated).then((data) => {
                if (
                  document.referrer &&
                  !document.referrer.includes("/nb-admin/")
                ) {
                  window.location.href = document.referrer;
                } else {
                  window.location.href = _resource_url || nb.base_url + "/nb-admin/" + resource_id;
                }
              });
            } else {
            }
          } else {
            nb.notify(data.message);
          }
        });
    },
    save() {
      this.redirect_on_submit = false;
      this.submit();
    },
    ai_field_has_value(field, lang) {
      const values = this.form_data[field];
      if (!values || typeof values !== "object") {
        return false;
      }
      return String(values[lang] || "").trim().length > 0;
    },
    ai_all_complete(lang) {
      return _ai_record_action_fields.every((field) =>
        this.ai_field_has_value(field, lang),
      );
    },
    refresh_ai_actions(lang) {
      this.ai_all_disabled = this.ai_all_complete(lang);
    },
    ai(field, lang) {
      this.busy = true;
      this.ai_busy_field = field;
      nb.api
        .post(nb.base_url + "/api/v1/openai/complete", {
          resource: this.resource_id,
          uuid: this.record_id,
          lang: lang,
          field: field,
        })
        .then((data) => {
          this.busy = false;
          this.ai_busy_field = null;
          this.me_busy = false;
          if (data.success) {
            this.form_data[field][lang] = data.completion;
            if (data.completion.length === 0) {
              nb.notify("Empty result");
            }
            if (this.lang === lang) {
              this.set_editors(lang);
            }
          } else {
            nb.notify(data.message);
          }
        })
        .catch((err) => {
          this.busy = false;
          this.ai_busy_field = null;
          this.me_busy = false;
          nb.notify(err.message || "Could not complete AI request");
        });
    },
    ai_all(lang) {
      this.sync_editors(lang);
      const values = Object.fromEntries(
        _ai_record_action_fields.map((field) => [field, this.form_data[field] || {}]),
      );
      this.busy = true;
      this.ai_busy_all = true;
      nb.api
        .post(nb.base_url + "/api/v1/openai/complete", {
          resource: this.resource_id,
          uuid: this.record_id,
          lang,
          field: "(all)",
          values,
        })
        .then((data) => {
          this.busy = false;
          this.ai_busy_all = false;
          if (!data.success) {
            nb.notify(data.message);
            return;
          }
          Object.entries(data.completions || {}).forEach(([field, value]) => {
            if (!this.form_data[field] || typeof this.form_data[field] !== "object") {
              this.form_data[field] = {};
            }
            if (!String(this.form_data[field][lang] || "").trim()) {
              this.form_data[field][lang] = value;
            }
          });
          this.lang = lang;
          this.set_editors(lang);
          this.refresh_ai_actions(lang);
        })
        .catch((err) => {
          this.busy = false;
          this.ai_busy_all = false;
          nb.notify(err.message || "Could not translate record");
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
              window.location.href = (_resource_url || nb.base_url + "/nb-admin/" + this.resource_id) + "/" + uuid;
            });
          } else {
            nb.notify(data.message);
          }
        });
    },
    delete_record() {
      nb.api
        .delete(
          nb.base_url + "/api/v1/" + this.resource_id + "/" + this.record_id
        )
        .then((data) => {
          if (data.success) {
            nb.system_message(nb.text.record_deleted);
            window.location.href = _resource_url || nb.base_url + "/nb-admin/" + resource_id;
          } else {
            nb.notify(data.message);
          }
        });
    },
    ...nb.forms,
  }));
});
