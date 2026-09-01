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
    filter_values: {},
    record_count() {
      return Object.keys(_records).length;
    },
    filtered_count() {
      return Object.keys(this.filtered_records).length;
    },
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
    has_active_query() {
      return this.search_regex !== null || Object.values(this.filter_values).some(value => value !== "");
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
    is_system_field(field_id) {
      return field_id === "_created" || field_id === "_modified";
    },
    field_class(field_id) {
      return this.is_system_field(field_id) ? "text-xs text-neutral-500" : "";
    },
    sort_records() {
      const field = this.sort_field;
      if (!field) {
        return;
      }

      const val = ([_, obj]) => (obj[field] ?? "").toString().toLowerCase();
      const entries = Object.entries(this.filtered_records);
      if (entries.length < 1) {
        this.set_page_records();
        return;
      }

      const first_val = val(entries[0]);
      const all_equal = entries.every((e) => val(e) === first_val);
      if (all_equal) {
        this.set_page_records();
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
            this.apply_pipeline();
          } else {
            nb.notify(data.message);
          }
        });
    },
    search(term) {
      this.search_term = term.toLowerCase();
      this.page = 1;
      if (term.length < 3) {
        this.search_regex = null;
        this.apply_pipeline();
        return;
      }
      const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      this.search_regex = new RegExp(`(${escaped})`, "gi");
      this.apply_pipeline();
    },
    record_matches_filters(record) {
      return Object.entries(this.filter_values).every(([field_id, selected]) => {
        if (selected === "") {
          return true;
        }
        const raw = record._filter_values?.[field_id] ?? "";
        if (Array.isArray(raw)) {
          return raw.map(String).includes(selected);
        }
        return String(raw) === selected;
      });
    },
    record_matches_search(record) {
      if (!this.search_regex) {
        return true;
      }
      return Object.keys(_fields).some(field_id => {
        const value = record[field_id];
        return typeof value === "string" && value.toLowerCase().includes(this.search_term);
      });
    },
    apply_pipeline() {
      this.filtered_records = Object.fromEntries(
        Object.entries(_records).filter(([_, record]) =>
          this.record_matches_filters(record) && this.record_matches_search(record)
        )
      );
      if (this.sort_field) {
        this.sort_records();
        return;
      }
      this.set_page_records();
    },
    change_filter(field_id, value) {
      this.filter_values[field_id] = String(value);
      this.page = 1;
      const url = new URL(window.location.href);
      url.searchParams.set(`filter[${field_id}]`, String(value));
      window.history.replaceState({}, "", url);
      this.apply_pipeline();
    },
    init_filters() {
      const params = new URLSearchParams(window.location.search);
      let normalize_url = false;
      for (const [field_id, filter] of Object.entries(_filters)) {
        const key = `filter[${field_id}]`;
        const has_url_value = params.has(key);
        const candidate = has_url_value ? params.get(key) : (filter.default ?? "");
        const value = String(candidate ?? "");
        const valid = value === "" || Object.prototype.hasOwnProperty.call(filter.options, value);
        this.filter_values[field_id] = valid ? value : "";
        if (has_url_value && !valid) {
          params.set(key, "");
          normalize_url = true;
        }
      }
      if (normalize_url) {
        const query = params.toString();
        window.history.replaceState({}, "", `${window.location.pathname}${query ? `?${query}` : ""}${window.location.hash}`);
      }
    },
    highlight(txt) {
      if (Array.isArray(txt)) {
        txt = txt.map(item =>
          item !== null && typeof item === 'object'
            ? (item.date || item.name || item.title || '')
            : item
        ).filter(Boolean).join(', ');
      }
      const s = txt != null ? String(txt) : '';
      if (s.trim() === '' || s === '(empty)') {
        return '<span class="select-none text-neutral-300" title="[#text No value#]" aria-label="[#text No value#]">&mdash;</span>';
      }
      if (!this.search_regex) {
        return s;
      }
      if (!s.toLowerCase().includes(this.search_term)) {
        return s;
      }
      return s.replace(this.search_regex, '<span class="bg-yellow-200">$1</span>');
    },
    init() {
      this.init_filters();
      if (_default_sort_field && _fields[_default_sort_field]) {
        this.sort_field = _default_sort_field;
        this.sort_asc = _default_sort_order.toLowerCase() !== "desc";
      }
      this.apply_pipeline();
    },
  }));
});
