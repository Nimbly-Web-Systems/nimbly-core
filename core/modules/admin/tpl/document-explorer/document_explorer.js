document.addEventListener("alpine:init", () => {
  Alpine.data("document_explorer", () => ({
    config: _documents,
    documents: _documents.documents,
    search_term: "",
    sort: _documents.fields.position ? "manual" : "title",
    layout: "list",
    category: "",
    uploads: [],
    drag_files: false,
    drag_depth: 0,
    dragging: null,
    drop_before: null,
    drop_target: null,
    init() {
      try {
        this.layout = localStorage.getItem("nb.document_explorer.layout") === "grid" ? "grid" : "list";
      } catch (error) {}
      const category = new URLSearchParams(window.location.search).get("category") ?? "";
      this.category = Object.prototype.hasOwnProperty.call(this.config.categories, category) ? category : "";
    },
    api_url(uuid = "") {
      return `${nb.base_url}/api/v1/${_resource_id}${uuid ? "/" + uuid : ""}`;
    },
    view_url(doc) {
      return `${nb.base_url}/nb-admin/${_resource_id}/${doc.uuid}?view=1`;
    },
    edit_url(doc) {
      return `${nb.base_url}/nb-admin/${_resource_id}/${doc.uuid}`;
    },
    download_url(doc) {
      return `${nb.base_url}/download/${doc.file}`;
    },
    // A page is shown whole, a picture fills the tile.
    thumb_url(doc, width, height) {
      return `${nb.base_url}/img/${doc.file}/${width}x${height}${this.is_pdf(doc) ? "f" : "c"}`;
    },
    is_pdf(doc) {
      return doc.type === "application/pdf";
    },
    has_categories() {
      return !!this.config.fields.category && Object.keys(this.config.categories).length > 0;
    },
    category_label() {
      return this.config.categories[this.category] ?? "";
    },
    count_in(value) {
      return this.documents.filter((doc) => doc.category === value).length;
    },
    set_category(value) {
      this.category = value;
      const url = new URL(window.location.href);
      if (value === "") {
        url.searchParams.delete("category");
      } else {
        url.searchParams.set("category", value);
      }
      window.history.replaceState({}, "", url);
    },
    set_layout(layout) {
      this.layout = layout;
      try {
        localStorage.setItem("nb.document_explorer.layout", layout);
      } catch (error) {}
    },
    visible() {
      const term = this.search_term.trim().toLowerCase();
      const list = this.documents.filter((doc) =>
        (this.category === "" || doc.category === this.category) &&
        (term === "" || `${doc.title} ${doc.name}`.toLowerCase().includes(term))
      );
      const by = {
        manual: (a, b) => a.position - b.position,
        title: (a, b) => (a.title || a.name).localeCompare(b.title || b.name),
        modified: (a, b) => b.modified - a.modified,
        size: (a, b) => b.size - a.size,
      }[this.sort];
      return list.sort(by);
    },
    has_preview(doc) {
      return doc.file !== "" && (doc.type.startsWith("image/") || (this.is_pdf(doc) && this.config.pdf_preview));
    },
    extension(doc) {
      const ext = doc.name.includes(".") ? doc.name.split(".").pop() : "";
      return ext !== "" && ext.length <= 4 ? ext : "file";
    },
    format_size(size) {
      if (!size) {
        return "";
      }
      if (size < 1024) {
        return size + " B";
      }
      return size < 1048576 ? (size / 1024).toFixed(1) + " KB" : (size / 1048576).toFixed(1) + " MB";
    },
    format_date(time) {
      return time ? new Date(time * 1000).toLocaleDateString() : "";
    },

    // Manual order needs the position field and the full, unsearched list in that order.
    can_order() {
      return !!this.config.fields.position && this.sort === "manual" && this.search_term.trim() === "";
    },
    can_drag() {
      return this.config.can_edit && (this.can_order() || this.has_categories());
    },
    is_file_drag(event) {
      return Array.from(event.dataTransfer?.types ?? []).includes("Files");
    },
    drag_enter(event) {
      if (!this.config.can_create || !this.is_file_drag(event)) {
        return;
      }
      this.drag_depth++;
      this.drag_files = true;
    },
    drag_over(event) {
      if (this.is_file_drag(event)) {
        event.dataTransfer.dropEffect = this.config.can_create ? "copy" : "none";
      }
    },
    drag_leave(event) {
      if (!this.is_file_drag(event)) {
        return;
      }
      this.drag_depth = Math.max(0, this.drag_depth - 1);
      this.drag_files = this.drag_depth > 0;
    },
    drag_start(event, doc) {
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/plain", doc.title || doc.name);
      this.dragging = doc.uuid;
    },
    drag_end() {
      this.dragging = null;
      this.drop_before = null;
      this.drop_target = null;
      this.drag_depth = 0;
      this.drag_files = false;
    },
    drag_over_document(event, doc) {
      if (this.dragging && this.can_order() && doc.uuid !== this.dragging) {
        this.drop_before = doc.uuid;
      }
    },
    drop_files(event) {
      const files = event.dataTransfer?.files ?? [];
      this.drag_end();
      if (this.config.can_create && files.length > 0) {
        this.upload_files(files, this.category);
      }
    },
    drop_on_document(event, doc) {
      if (this.is_file_drag(event)) {
        return this.drop_files(event);
      }
      const dragged = this.dragging;
      this.drag_end();
      if (!dragged || dragged === doc.uuid || !this.can_order()) {
        return;
      }
      const list = this.visible();
      const from = list.findIndex((item) => item.uuid === dragged);
      const to = list.findIndex((item) => item.uuid === doc.uuid);
      list.splice(to, 0, list.splice(from, 1)[0]);
      this.save_order(list);
    },
    drop_on_category(event, value) {
      if (this.is_file_drag(event)) {
        const files = event.dataTransfer.files;
        this.drag_end();
        if (this.config.can_create && files.length > 0) {
          this.upload_files(files, value);
        }
        return;
      }
      const doc = this.documents.find((item) => item.uuid === this.dragging);
      this.drag_end();
      if (doc && value !== "" && doc.category !== value) {
        this.move_to_category(doc, value);
      }
    },

    // Only the records whose place changed are saved.
    save_order(list) {
      list.forEach((doc, ix) => {
        const position = (ix + 1) * 10;
        if (doc.position === position) {
          return;
        }
        doc.position = position;
        nb.api.put(this.api_url(doc.uuid), { [this.config.fields.position]: position }).then((res) => {
          if (!res.success) {
            nb.notify(res.message);
          }
        });
      });
    },
    move_to_category(doc, value) {
      nb.api.put(this.api_url(doc.uuid), { [this.config.fields.category]: value }).then((res) => {
        if (!res.success) {
          nb.notify(res.message);
          return;
        }
        doc.category = value;
        nb.notify(_documents_text.moved_to + " " + this.config.categories[value]);
      });
    },
    delete_document(doc) {
      nb.api.delete(this.api_url(doc.uuid)).then((res) => {
        if (!res.success) {
          nb.notify(res.message);
          return;
        }
        this.documents = this.documents.filter((item) => item.uuid !== doc.uuid);
        nb.notify(nb.text.record_deleted);
      });
    },

    // A few files at a time: each one is uploaded, then gets its own record.
    async upload_files(file_list, category) {
      const files = Array.from(file_list);
      let next = 0;
      const worker = async () => {
        while (next < files.length) {
          await this.upload_file(files[next++], category);
        }
      };
      await Promise.all(files.slice(0, 3).map(worker));
    },
    async upload_file(file, category) {
      const upload = Alpine.reactive({ id: `${Date.now()}-${Math.random()}`, name: file.name, progress: 0 });
      this.uploads.push(upload);
      const done = () => {
        this.uploads = this.uploads.filter((item) => item.id !== upload.id);
      };
      const res = await nb.upload.upload(file, null, {
        quiet: true,
        on_progress: (fraction) => { upload.progress = fraction; },
      }).catch(() => ({ success: false }));
      if (!res.success) {
        nb.notify(file.name + ": " + (res.message || _documents_text.upload_failed));
        return done();
      }
      const fields = this.config.fields;
      const data = { [fields.file]: res.files.uuid };
      if (fields.title) {
        data[fields.title] = file.name.replace(/\.[^.]+$/, "");
      }
      if (fields.category && category !== "") {
        data[fields.category] = category;
      }
      if (fields.position) {
        data[fields.position] = Math.max(0, ...this.documents.map((doc) => doc.position)) + 10;
      }
      const created = await nb.api.post(this.api_url(), data).catch(() => ({ success: false }));
      done();
      const record = created.success ? Object.values(created[_resource_id] ?? {})[0] : null;
      if (!record) {
        nb.notify(file.name + ": " + (created.message || _documents_text.upload_failed));
        return;
      }
      this.documents.push({
        uuid: record.uuid,
        title: fields.title ? data[fields.title] : "",
        file: res.files.uuid,
        name: res.files.name,
        type: res.files.type,
        size: res.files.size,
        category: data[fields.category] ?? "",
        position: data[fields.position] ?? 0,
        modified: Math.floor(Date.now() / 1000),
      });
    },
  }));
});
