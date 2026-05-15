document.addEventListener("alpine:init", () => {
  Alpine.data("utility_table", () => ({
    page: 1,
    page_size: 50,
    rows: [],
    filtered_rows: [],
    sort_index: null,
    sort_asc: true,
    search_term: "",
    search_regex: null,
    init() {
      this.rows = Array.from(this.$refs.body.querySelectorAll("tr"));
      this.filtered_rows = [...this.rows];
      this.render();
    },
    page_count() {
      return Math.ceil(this.filtered_rows.length / this.page_size) || 1;
    },
    total_count() {
      return this.filtered_rows.length;
    },
    render() {
      const offset = (this.page - 1) * this.page_size;
      const limit = offset + parseInt(this.page_size);
      this.rows.forEach((row) => row.classList.add("hidden"));
      this.filtered_rows.slice(offset, limit).forEach((row) => row.classList.remove("hidden"));
    },
    search(term) {
      this.search_term = term.toLowerCase();
      this.page = 1;
      if (this.search_term.length < 3) {
        this.search_regex = null;
        this.filtered_rows = [...this.rows];
        this.render();
        return;
      }
      const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      this.search_regex = new RegExp(`(${escaped})`, "gi");
      this.filtered_rows = this.rows.filter((row) => row.textContent.toLowerCase().includes(this.search_term));
      this.render();
    },
    toggle_sort(index) {
      if (this.sort_index === index) {
        this.sort_asc = !this.sort_asc;
      } else {
        this.sort_index = index;
        this.sort_asc = true;
      }
      const direction = this.sort_asc ? 1 : -1;
      this.filtered_rows.sort((a, b) => {
        const a_text = (a.children[index]?.textContent || "").trim().toLowerCase();
        const b_text = (b.children[index]?.textContent || "").trim().toLowerCase();
        return a_text.localeCompare(b_text, undefined, { numeric: true }) * direction;
      });
      this.filtered_rows.forEach((row) => this.$refs.body.appendChild(row));
      this.render();
    },
    is_sorted_asc(index) {
      return this.sort_index === index && this.sort_asc;
    },
    is_sorted_desc(index) {
      return this.sort_index === index && !this.sort_asc;
    },
    prev() {
      if (this.page > 1) {
        this.page -= 1;
        this.render();
      }
    },
    next() {
      if (this.page < this.page_count()) {
        this.page += 1;
        this.render();
      }
    },
  }));
});
