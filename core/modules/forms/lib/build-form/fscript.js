document.addEventListener("alpine:init", () => {
Alpine.data("[#_bf_name#]_form", (resource_id = "(empty)") => ({
    resource_id: resource_id,
    form_elem: null,
    form_key: null,
    file_uuid: null,
    uploading: false,
    submitting: false,
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
        if (this.submitting) {
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
    ...nb.forms,
  }));
})
