var _bf_uuid = "[#get _bf_uuid#]";
var _initial_lang = "[#get record.lang default=en#]";
var _translation_mode = "[#get translation_mode#]";
var _ai_record_action = [#if data.ai_record_action=(not-empty) echo=true echo_else=false#];
var _ai_record_action_fields = [#fmt var=data.ai_record_action_fields type=json empty=[]#];
var _translation_languages = [#fmt var=data.translation_languages type=json empty=[]#];
var _frecord = [#fmt var=_frecord json#];

document.addEventListener("alpine:init", () => {
Alpine.data("[#_bf_name#]_form", (resource_id = "(empty)", record_id = "") => ({
    resource_id: resource_id,
    record_id: record_id,
    lang: _initial_lang,
    busy: false,
    ai_busy_field: null,
    ai_busy_all: false,
    translation_empty: {},
    form_elem: null,
    form_key: null,
    file_uuid: null,
    uploading: false,
    submitting: false,

    init() {
      if (!this.record_id) {
        return;
      }
      this.form_data = _frecord || {};
      this.initialize_translation_empty();
      this.$watch("lang", (lang) => {
        this.set_editors(lang);
      });
      this.$nextTick(() => {
        this.set_editors(this.lang);
      });
    },

    set_form_submitting(is_submitting) {
      this.submitting = is_submitting;
      if (!this.form_elem) {
        return;
      }
      this.form_elem.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
        button.disabled = is_submitting;
        button.setAttribute("aria-busy", is_submitting ? "true" : "false");
        button.classList.toggle("opacity-60", is_submitting);
        button.classList.toggle("cursor-not-allowed", is_submitting);
      });
    },
    finish_submit() {
      this.uploading = false;
      this.set_form_submitting(false);
    },
    handle_upload_ready(e) {
      if (typeof e.detail !== "undefined" && e.detail.success) {
        e.detail.files.size = e.detail.files.size || 0;
        this.post_entry();
      }
    },
    post_entry() {
      this.set_form_submitting(true);
      var d = new Date();
      nb.api
        .post(nb.base_url + "/api/v1/" + resource_id + "?key=" + this.form_key, {
          status: "[#_bf_status#]",
          [#if _bf_upload_field=(not-empty) echo="[#_bf_upload_field#]: this.file_uuid,"#]
          entry_date: d.toISOString().split("T")[0],
          ...this.form_data,
          ...nb.edit.get_field_values(this.form_elem),
        })
        .then((data) => {
          this.uploading = false;
          if (data.success) {
            this.form_data = {};
            nb.notify(
              "[#_bf_success_message#]"
            );
          } else {
            nb.notify(data.message);
          }
          this.finish_submit();
        })
        .catch((error) => {
          this.finish_submit();
          throw error;
        });
    },
    submit(e) {
        if (this.submitting || this.busy) {
          return;
        }
        if (this.record_id) {
          this.edit_submit();
          return;
        }
        [#if _bf_upload_field=(not-empty) echo=this.submit_with_upload(e);#]
        [#if _bf_upload_field=(empty) echo=this.submit_without_upload(e);#]
    },
    submit_without_upload(e) {
      this.form_elem = e.target;
      this.form_key = this.form_elem.querySelectorAll(
        "input[type=hidden][name=form_key]"
      )[0].value;
      this.set_form_submitting(true);
      this.post_entry();
    },
    submit_with_upload(e) {
      this.uploading = true;
      this.form_elem = e.target;
      this.form_key = this.form_elem.querySelectorAll(
        "input[type=hidden][name=form_key]"
      )[0].value;
      this.set_form_submitting(true);
      var input_files = this.form_elem.querySelectorAll(
        "input[type=file]"
      );

      _uploading = false;
      for (let ix = 0; ix < input_files.length; ix++) {
        let e = input_files[ix];
        if (e.files.length > 0) {
            _uploading = true;
            this.upload(e.files[0], e);
            break;
        }
      }

      if (!_uploading) {
        this.post_entry();
      }
    },
    upload(file, e) {
      if (e.dataset.nbMaxFileSize && file.size > e.dataset.nbMaxFileSize) {
        nb.notify("File too large.");
        this.finish_submit();
        return;
      }
      var data = new FormData();
      data.append("file", file);
      fetch(nb.upload.api_url + "?key=" + this.form_key, {
        method: "POST",
        body: data,
      })
        .then((res) => res.json())
        .then((res) => {
          if (res.success) {
            this.file_uuid = res.files.uuid;
            this.post_entry();
            e.value = null;
          } else {
            nb.notify("Could not upload file.");
            this.finish_submit();
          }
        })
        .catch((error) => {
          this.finish_submit();
          throw error;
        });
    },

    // --- Edit mode: prefill + PUT, i18n rich-text sync, AI-assist ---
    // Mirrors core/modules/admin/tpl/edit-resource-form/form_edit.js so both
    // systems behave identically for a record that already exists.

    edit_submit() {
      this.busy = true;
      this.sync_editors(this.lang);
      const payload = {
        ...this.form_data,
        ...this.get_editor_values(),
      };
      nb.api
        .put(nb.base_url + "/api/v1/" + this.resource_id + "/" + this.record_id, payload)
        .then((data) => {
          this.busy = false;
          if (data.success) {
            nb.notify(nb.text.record_updated || "[#text Saved#]");
          } else {
            nb.notify(data.message);
          }
        });
    },
    save() {
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

    ...nb.forms,
  }));
})
