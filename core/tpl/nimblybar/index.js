const nb_bar = document.getElementById("nb-bar");
const nb_modal_insert_media = document.getElementById("nb-modal-insert-media");

document.getElementById("nb_nav_toggler").addEventListener("click", () => {
  te.Sidenav.getInstance(nb_bar).toggleSlim();
});

document.getElementById("nb_edit_toggler").addEventListener("click", (e) => {
  e.currentTarget.classList.toggle("bg-clight/50");
  nb.edit.toggle();
});

document.getElementById("nb_edit_save").addEventListener("click", (e) => {
  e.currentTarget.setAttribute("disabled", true);
  nb.edit.save();
});

document.getElementById("nb_edit_insert_media").addEventListener("click", (e) => {
  /* popup img selector */
});

nb_bar.addEventListener("expanded.te.sidenav", (event) => {
  nb.api.post("[base-url]/api/v1/session", { nb_bar_slim: false });
  nb_bar.show_edit_menu(nb.edit.enabled);
});

nb_bar.addEventListener("collapsed.te.sidenav", (event) => {
  nb.api.post("[base-url]/api/v1/session", { nb_bar_slim: true });
});

nb_bar.addEventListener("expand.te.sidenav", (event) => {
  nb_bar.classList.add("px-2");
});

nb_bar.addEventListener("collapse.te.sidenav", (event) => {
  nb_bar.classList.remove("px-2", "data-[te-sidenav-slim='false']:px-2");
});

nb_bar.show_edit_menu = function (show = true) {
  const el = document.getElementById("nb_edit_menu");
  const ul = el.querySelector("ul");
  const emenu = te.Collapse.getInstance(ul);
  show ? emenu.show() : emenu.hide();
};

const alpine_media_insert = function () {
  Alpine.data("media_insert", () => ({
    file_info: null,
    handle_upload_ready(e) {
      if (typeof e.detail !== "undefined" && e.detail.success) {
        e.detail.files.size = e.detail.files.size || 0;
        this.file_info = e.detail.files;
      }
    },
    delete_file(uuid) {
      nb.api.delete(nb.base_url + "/api/v1/.files/" + uuid).then((data) => {
        if (data.success) {
          nb.notify(nb.text.file_deleted);
          this.file_info = null;
        } else {
          nb.notify(data.message);
        }
      });
    },
    insert_media() {
      const img_aspect = this.file_info.aspect;
      const img_mode = "w";
      const img_sizes = [
        120, 180, 240, 320, 480, 640, 800, 960, 1120, 1280, 1440, 1600, 1760,
        1920,
      ];
      var data = { uuid: this.file_info.uuid };
      for (i = 0; i < img_sizes.length; i++) {
        data["img_size" + i] = img_sizes[i] + img_mode;
      }
      var html = nb.populate_template("nb_media_insert_img_tpl", data);
      nb.edit.insert_html(html);
      const m = te.Modal.getInstance(nb_modal_insert_media);
      m.hide();
    },
  }));
};

typeof Alpine === "undefined"
  ? document.addEventListener("alpine:initializing", () => {
      alpine_media_insert();
    })
  : alpine_media_insert();
