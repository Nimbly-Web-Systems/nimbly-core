Alpine.data("resource_table", (resource_id) => ({
  resource_id: resource_id,
  delete_record(el, uuid) {
    const row_el = el.closest("tr");
    nb.api
      .delete(nb.base_url + "/api/v1/" + resource_id + "/" + uuid)
      .then((data) => {
        if (data.success) {
          row_el.remove();
          nb.notify(nb.text.record_deleted);
        }
      });
  },
}));
