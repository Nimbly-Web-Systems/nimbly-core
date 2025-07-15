document.addEventListener("alpine:init", () => {
  Alpine.data("data_table", () => ({
    busy: false,
    offset: 0,
    page_size: _page_size,
    page: 1,
    sort_field: null,
    sort_asc: true,
    search_term: "",
    filtered_records: {},
    page_records: {},
    search_regex: null,
    page_count() {
      return Math.ceil(
        Object.keys(this.filtered_records).length / this.page_size
      );
    },
    set_page_records() {
      this.page_size = parseInt(this.page_size);
      const max_page =
        Math.ceil(Object.keys(this.filtered_records).length / this.page_size) ||
        1;
      if (this.page > max_page) {
        this.page = max_page;
      }
      this.offset = (this.page - 1) * this.page_size;
      const chunk = Object.entries(this.filtered_records).slice(
        this.offset,
        this.offset + this.page_size
      );
      this.page_records = Object.fromEntries(chunk);
    },
    store_page_size() {
      if (this.page > this.page_count()) {
        this.page = this.page_count();
      }
      this.scroll_lock();
      nb.api.post(nb.base_url + "/api/v1/session", {
        "datatable.entries": this.page_size,
      });
      this.set_page_records();
    },
    prev() {
      this.page -= 1;
      this.set_page_records();
      this.scroll_lock();
    },
    next() {
      this.page += 1;
      this.set_page_records();
      this.scroll_lock();
    },
    scroll_lock() {
      const y1 = this.$refs.btn_next_page.getBoundingClientRect().top;
      this.$nextTick(() => {
        const delta = this.$refs.btn_next_page.getBoundingClientRect().top - y1;
        window.scrollBy({ top: delta, behavior: "instant" });
      });
    },
    total_count() {
      return Object.keys(this.filtered_records).length;
    },
    toggle_sort(field_id) {
      if (this.sort_field === field_id) {
        this.sort_asc = !this.sort_asc;
      } else {
        this.sort_field = field_id;
        this.sort_asc = true;
      }
      this.sort_records();
    },
    is_sorted_asc(field_id) {
      return this.sort_field === field_id && this.sort_asc;
    },
    is_sorted_desc(field_id) {
      return this.sort_field === field_id && !this.sort_asc;
    },
    sort_records() {
      const field = this.sort_field;
      if (!field) {
        return;
      }

      const val = ([_, obj]) => (obj[field] ?? "").toString().toLowerCase();
      const entries = Object.entries(this.filtered_records);

      const first_val = val(entries[0]);
      const all_equal = entries.every((e) => val(e) === first_val);
      if (all_equal) {
        return;
      }

      entries.sort((a, b) => val(a).localeCompare(val(b)));
      if (!this.sort_asc) {
        entries.reverse();
      }
      this.filtered_records = Object.fromEntries(entries);
      this.set_page_records();
    },
    delete_record(record_id) {
      nb.api
        .delete(`${nb.base_url}/api/v1/${_resource_id}/${record_id}`)
        .then((data) => {
          if (data.success) {
            nb.notify(nb.text.record_deleted);
            delete _records[record_id];
            delete this.filtered_records[record_id];
            this.set_page_records();
          } else {
            nb.notify(data.message);
          }
        });
    },
    search(term) {
      this.search_term = term.toLowerCase();
      if (term.length < 3) {
        this.search_regex = null;
        this.filtered_records = { ..._records };
        this.set_page_records();
        return;
      }

      const result = {};
      for (const [id, rec] of Object.entries(_records)) {
        for (const field of Object.keys(_fields)) {
          const val = rec[field];
          if (
            typeof val === "string" &&
            val.toLowerCase().includes(this.search_term)
          ) {
            result[id] = rec;
            break;
          }
        }
      }
      const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      this.search_regex = new RegExp(`(${escaped})`, "gi");
      this.filtered_records = result;
      this.set_page_records();
    },
    highlight(txt) {
      if (!this.search_regex) {
        return txt;
      }

      if (!txt.toLowerCase().includes(this.search_term)) {
        return txt; //should not happen but it does
      }

      return txt.replace(
        this.search_regex,
        '<span class="bg-yellow-200">$1</span>'
      );
    },
    init() {
      this.filtered_records = { ..._records };

      const first_field_id = Object.keys(_fields).find((f) => {
        const field = _fields[f];
        return field.sortable === undefined || field.sortable;
      });

      if (first_field_id) {
        this.sort_field = first_field_id;
        this.sort_asc = true;
        this.sort_records();
      } else {
        this.set_page_records();
      }
    },
  }));
});
