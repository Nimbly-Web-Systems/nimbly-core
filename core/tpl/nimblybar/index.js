const nb_bar = document.getElementById("nb-bar");
const nb_modal_insert_media = document.getElementById("nb-modal-insert-media");
const nb_edit_insert_media = document.getElementById("nb_edit_insert_media");

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

if (nb_edit_insert_media) {
  nb_edit_insert_media.addEventListener("click", (e) => {
    nb.edit.store_caret_pos();
  });
}

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

const alpine_media_insert = function () {
  Alpine.data("media_insert", () => ({
    caret: null,
    caret_pos: 0,
    hide_save_button: true,
    insert_doc_html() {
      return nb.populate_template("nb_media_insert_doc_tpl", {
        uuid: this.file_info.uuid,
        name: this.file_info.name,
        title: this.file_info.title || this.file_info.name,
        description: this.file_info.description || this.file_info.size.fileSize(1)
      });
    },
    insert_vid_html() {
      return nb.populate_template("nb_media_insert_vid_tpl", {
        uuid: this.file_info.uuid,
        type: 'video/' + this.vid_type(),
        width: this.file_info.width,
        height: this.file_info.height
      });
    },
    insert_img_html() {
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
          nb.base_url +
            "/img/" +
            this.file_info.uuid +
            "/" +
            w +
            img_mode +
            " " +
            w +
            "w"
        );
        if (this.file_info.width < w) {
          break;
        }
      }
      const tpl_name = img_aspect >= 1.0? 'nb_media_insert_img_landscape_tpl' : 'nb_media_insert_img_portrait_tpl';
      return nb.populate_template(tpl_name, {
        uuid: this.file_info.uuid,
        width: this.file_info.width,
        height: this.file_info.height,
        sizes: media_sizes.join(", "),
        src: src,
        srcset: srcset.join(", "),
      });
    },
    insert_media() {
      const m = te.Modal.getInstance(nb_modal_insert_media);
      if (!nb.edit.active_editor) {
        m.hide();
        return;
      }
      nb.api.put(nb.base_url + "/api/v1/.files_meta/" + this.file_info.uuid, {
        title: this.file_info.title,
        description: this.file_info.description,
      });

      let html = "";
      const file_type = this.file_type();
      if (file_type === "img") {
        html = this.insert_img_html();
      } else if (file_type === "doc") {
        html = this.insert_doc_html();
      } else if (file_type === "vid") {
        html = this.insert_vid_html();
      }

      nb.edit.restore_caret_pos();
      nb.edit.insert_html(html);
      m.hide();
    },
    ...nb.media_library,
  }));
};

typeof Alpine === "undefined"
  ? document.addEventListener("alpine:initializing", () => {
      alpine_media_insert();
    })
  : alpine_media_insert();
