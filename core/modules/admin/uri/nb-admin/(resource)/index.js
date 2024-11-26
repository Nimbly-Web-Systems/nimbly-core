Alpine.data("resource_table", (resource_id) => ({
  entries: 50,
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
  on_datatable_render() {
    console.log('render');
  }
}));

const nb_datatable = document.getElementById('nb-datatable');
nb_datatable.addEventListener('render.te.datatable', (e) => {
  const entries_select = document.querySelector('#nb-datatable select[name=entries]');
  if (entries_select && entries_select.value) {
    nb.api.post(nb.base_url + '/api/v1/session', {"datatable.entries": entries_select.value});
  }
});
