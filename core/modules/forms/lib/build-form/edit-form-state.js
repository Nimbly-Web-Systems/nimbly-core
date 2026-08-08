// Plain JS, no shortcode syntax — kept separate from fscript.js (a template
// file) specifically so it stays directly unit-testable via Node's `import()`.
// Reads _initial_lang / _frecord / _translation_mode / _ai_record_action_fields
// / _translation_languages / _resource_url as globals, declared by whichever
// template includes this file (build-form's fscript.js).
function nb_build_form_edit_state(resource_id, record_id) {
  return {
    resource_id: resource_id,
    record_id: record_id,
    lang: _initial_lang,
    redirect_on_submit: true,
    busy: false,
    ai_busy_field: null,
    ai_busy_all: false,
    translation_empty: {},

    init_edit_state() {
      this.form_data = _frecord || {};
      this.initialize_translation_empty();
      this.$watch("lang", (lang) => {
        this.set_editors(lang);
      });
      this.$nextTick(() => {
        this.set_editors(this.lang);
      });
    },

    edit_submit() {
      this.busy = true;
      this.sync_editors(this.lang);
      const payload = {
        ...this.form_data,
        ...this.get_editor_values(),
      };
      if (_translation_mode === "field") {
        // lang/translations are bookkeeping added for rendering by
        // get_resource_record_sc() — never persist them back onto the record.
        delete payload.lang;
        delete payload.translations;
      }
      nb.api
        .put(nb.base_url + "/api/v1/" + this.resource_id + "/" + this.record_id, payload)
        .then((data) => {
          this.busy = false;
          if (!data.success) {
            nb.notify(data.message);
            return;
          }
          if (!this.redirect_on_submit) {
            return;
          }
          nb.system_message(nb.text.record_updated).then(() => {
            if (document.referrer && !document.referrer.includes("/nb-admin/")) {
              window.location.href = document.referrer;
            } else {
              window.location.href = _resource_url || nb.base_url + "/nb-admin/" + this.resource_id;
            }
          });
        });
    },
    save() {
      this.redirect_on_submit = false;
      this.edit_submit();
    },
    set_editors(lang) {
      if (!this.$el) {
        return;
      }
      const editors = this.$el.querySelectorAll("[data-nb-edit]");
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

        el.innerHTML = this.editor_html_for_display(value);
        nb.edit.init_editor(el, true);
      });
    },
    editor_html_for_display(value) {
      const base_url = nb.base_url === "/" ? "" : nb.base_url.replace(/\/$/, "");
      if (!base_url) {
        return String(value || "");
      }
      return String(value || "").replace(
        /(["', (])\/(img\/[0-9a-z]{20,32}\/|download\/[0-9a-z]{20,32}(?=["', )<]))/gi,
        `$1${base_url}/$2`,
      );
    },
    editor_html_for_storage(value) {
      const base_url = nb.base_url === "/" ? "" : nb.base_url.replace(/\/$/, "");
      if (!base_url) {
        return String(value || "");
      }
      return String(value || "").replaceAll(`${base_url}/img/`, "/img/")
        .replaceAll(`${base_url}/download/`, "/download/");
    },
    sync_editors(lang) {
      if (!this.$el) {
        return;
      }
      const editors = this.$el.querySelectorAll("[data-nb-edit]");
      editors.forEach((el) => {
        const parts = el.dataset.nbEdit.split(".");
        const field = parts[parts.length - 1];
        const field_data = this.form_data[field];

        if (!field_data || typeof field_data !== "object") {
          return;
        }

        this.form_data[field][lang] = this.editor_html_for_storage(el.innerHTML.trim());
      });
    },
    get_editor_values() {
      const editor_values = nb.edit.get_field_values(this.$el);

      Object.keys(editor_values).forEach((field) => {
        const field_data = this.form_data[field];

        if (field_data && typeof field_data === "object" && typeof editor_values[field] !== "object") {
          delete editor_values[field];
        } else if (typeof editor_values[field] === "string") {
          editor_values[field] = this.editor_html_for_storage(editor_values[field]);
        }
      });

      return editor_values;
    },
    initialize_translation_empty() {
      this.translation_empty = Object.fromEntries(
        _ai_record_action_fields.map((field) => [
          field,
          Object.fromEntries(
            _translation_languages.map((language) => [
              language,
              this.translation_value_is_empty(this.form_data[field]?.[language]),
            ]),
          ),
        ]),
      );
    },
    translation_value_is_empty(value) {
      const normalized = String(value || "")
        .replace(/<br\s*\/?\s*>/gi, "")
        .replace(/<\/?(?:p|div)[^>]*>/gi, "")
        .replace(/&nbsp;|&#160;/gi, " ");
      return normalized.trim().length === 0;
    },
    translation_field_empty(field, lang) {
      return this.translation_empty[field]?.[lang] === true;
    },
    has_empty_translation_fields(lang) {
      return _ai_record_action_fields.some((field) =>
        this.translation_field_empty(field, lang),
      );
    },
    set_translation_empty(field, lang, value) {
      if (!this.translation_empty[field]) {
        this.translation_empty[field] = {};
      }
      this.translation_empty[field][lang] = this.translation_value_is_empty(value);
    },
    sync_ai_input(event, lang) {
      const field = event.target.name;
      if (!field || !_ai_record_action_fields.includes(field)) {
        return;
      }
      if (!this.form_data[field] || typeof this.form_data[field] !== "object") {
        this.form_data[field] = {};
      }
      this.form_data[field][lang] = event.target.value;
      this.set_translation_empty(field, lang, this.form_data[field][lang]);
    },
    sync_ai_editor(event, lang) {
      const field = event.target.dataset.nbEdit?.split(".").pop();
      if (!field || !_ai_record_action_fields.includes(field)) {
        return;
      }
      this.form_data[field][lang] = event.detail.value;
      this.set_translation_empty(field, lang, event.detail.value);
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
          if (data.success) {
            this.form_data[field][lang] = data.completion;
            this.set_translation_empty(field, lang, data.completion);
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
              this.set_translation_empty(field, lang, value);
            }
          });
          this.lang = lang;
          this.set_editors(lang);
        })
        .catch((err) => {
          this.busy = false;
          this.ai_busy_all = false;
          nb.notify(err.message || "Could not translate record");
        });
    },
    delete_record() {
      nb.api
        .delete(nb.base_url + "/api/v1/" + this.resource_id + "/" + this.record_id)
        .then((data) => {
          if (data.success) {
            nb.system_message(nb.text.record_deleted);
            window.location.href = _resource_url || nb.base_url + "/nb-admin/" + this.resource_id;
          } else {
            nb.notify(data.message);
          }
        });
    },
  };
}

// Classic (non-module) script — this file is raw-included inside a plain
// <script> tag by fscript.js, so `function` declarations are already global.
// Set explicitly too so a Node `import()` of this file (as an ES module, for
// tests) can also reach it, since ESM top-level declarations stay private to
// the module otherwise.
globalThis.nb_build_form_edit_state = nb_build_form_edit_state;
