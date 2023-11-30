
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

document
  .getElementById("nb_edit_insert_media")
  .addEventListener("click", (e) => {
    nb.edit.store_caret_pos();
  });

nb_bar.addEventListener("expanded.te.sidenav", (event) => {
  nb.api.post(nb.base_url + "/api/v1/session", { nb_bar_slim: false });
  nb_bar.show_edit_menu(nb.edit.enabled);
});

nb_bar.addEventListener("collapsed.te.sidenav", (event) => {
  nb.api.post(nb.base_url + "/api/v1/session", { nb_bar_slim: true });
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

let nb_media = {};

const alpine_media_insert = function () {
  Alpine.data("media_insert", () => ({
    file_info: null,
    caret: null,
    caret_pos: 0,
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
          const el = document.getElementById('nb_media_item_' + uuid);
          if (el) {
            el.remove();
          }
        } else {
          nb.notify(data.message);
        }
      });
    },
    select_media(uuid) {
      nb.api.get(nb.base_url + "/api/v1/.files_meta/" + uuid).then((data) => {
        if (data.success) {
          this.file_info = data['.files_meta'][uuid];
          document.getElementById('nb_file_info').scrollIntoView();
        } 
      });

    },
    insert_media() {
      const m = te.Modal.getInstance(nb_modal_insert_media);
      if (!nb.edit.active_editor) {
        m.hide();
        return;
      }
      nb.api.put(nb.base_url + "/api/v1/.files_meta/" + this.file_info.uuid, {
        title: this.file_info.title, description: this.file_info.description
      });
      const img_aspect = this.file_info.aspect;
      const img_mode = "w";
      const img_sizes = [
        120, 180, 240, 320, 480, 640, 800, 960, 1120, 1280, 1440, 1600, 1760,
        1920,
      ];

      const editor_options = nb.edit.active_editor._nb_editor_options;

      let media_sizes = ["100vw"];
      if ("media_sizes" in editor_options) {
        const sl = editor_options.media_sizes.split(",");
        for (let s of sl) {
          const rule = s.split("-");
          media_sizes.unshift(
            "(min-width: " +
              nb.tw_breakpoints[rule[0]] +
              "px) " +
              rule[1] +
              "vw"
          );
        }
      }

      let src = nb.base_url + "/img/" + this.file_info.uuid + "/480" + img_mode;
      let srcset = [];
      for (let w of img_sizes) {
        srcset.push(
          nb.base_url + "/img/" + this.file_info.uuid + "/" + w + img_mode + ' ' + w + 'w'
        );
        if (this.file_info.width < w) {
          break;
        }
      }
      nb.edit.restore_caret_pos();
      nb.edit.insert_html(
        nb.populate_template("nb_media_insert_img_tpl", {
          uuid: this.file_info.uuid,
          width: this.file_info.width,
          height: this.file_info.height,
          sizes: media_sizes.join(', '),
          src: src,
          srcset: srcset.join(', '),
        })
      );
      m.hide();
    },
  }));
};

typeof Alpine === "undefined"
  ? document.addEventListener("alpine:initializing", () => {
      alpine_media_insert();
    })
  : alpine_media_insert();

