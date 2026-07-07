const nb_bar = document.getElementById("nb-bar");
const nb_modal_insert_media = document.getElementById("nb-modal-insert-media");
const nb_modal_settings = document.getElementById("nb-modal-settings");

function nb_bar_is_mobile() {
  return window.matchMedia("(max-width: 767px)").matches;
}

function nb_bar_set_page_layout(side, collapsed) {
  const page = document.getElementById("page");
  const expanded = "15rem";
  const compact = "2rem";
  const offset = collapsed ? compact : expanded;
  const mobile = nb_bar_is_mobile();
  if (page) {
    page.style.width = "";
    page.style.marginLeft = "";
    page.style.marginRight = "";
  }
  document.body.classList.add("nb-bar-layout");
  document.body.style.boxSizing = "border-box";
  document.body.style.paddingLeft = !mobile && side === "left" ? offset : "";
  document.body.style.paddingRight = !mobile && side === "right" ? offset : "";
  document.body.style.paddingBottom = mobile ? "4rem" : "";
}

function nb_bar_edit_menu(show = true) {
  const el = document.getElementById("nb_edit_menu");
  if (!el) {
    return;
  }
  const event = new CustomEvent("nb:edit-menu", {
    detail: { show: show },
    bubbles: true,
  });
  el.dispatchEvent(event);
}

function nb_bar_init_edit_controls() {
  if (document.getElementById("nb_edit_menu")) {
    document.querySelectorAll("[data-nb-edit-toggle]").forEach((button) => {
      button.addEventListener("click", (e) => {
        nb.edit.toggle();
        document.querySelectorAll("[data-nb-edit-toggle]").forEach((toggle_button) => {
          toggle_button.classList.toggle("bg-clight/50", nb.edit.enabled);
        });
        nb_bar_edit_menu(nb.edit.enabled);
      });
    });

    document.querySelectorAll("[data-nb-edit-save]").forEach((button) => {
      button.addEventListener("click", (e) => {
        document.querySelectorAll("[data-nb-edit-save]").forEach((save_button) => {
          save_button.setAttribute("disabled", true);
        });
        nb.edit.save();
      });
    });
  }

  document.querySelectorAll("[data-nb-edit-insert-media]").forEach((button) => {
    button.addEventListener("click", () => {
      if (nb.media_alpine) {
        nb.media_alpine.filter();
        nb.media_alpine.mode = "insert";
        nb.media_alpine.reset_tab();
      }
      nb.edit.store_caret_pos();
      nb.modal.open("nb-modal-insert-media");
    });
  });
}

const alpine_nimblybar = function () {
  Alpine.data("nimblybar", (side, initial_collapsed) => ({
    side: side === "left" ? "left" : "right",
    collapsed: initial_collapsed === true,
    is_mobile: false,
    mobile_panel: null,
    account_open: false,
    resources_open: true,
    edit_open: false,
    init() {
      this.is_mobile = nb_bar_is_mobile();
      nb_bar_set_page_layout(this.side, this.collapsed);
      window.addEventListener("resize", () => {
        const mobile = nb_bar_is_mobile();
        this.is_mobile = mobile;
        if (!mobile) {
          this.mobile_panel = null;
        }
        nb_bar_set_page_layout(this.side, this.collapsed);
      });
      this.$watch("collapsed", (value) => {
        nb_bar_set_page_layout(this.side, value);
        nb.api.post(nb.base_url + "/api/v1/session", { nb_bar_slim: value });
        if (!value) {
          nb_bar_edit_menu(nb.edit && nb.edit.enabled);
        }
      });
      this.$el.addEventListener("nb:edit-menu", (event) => {
        this.edit_open = event.detail.show === true;
      });
    },
    toggle() {
      if (this.is_mobile) {
        this.mobile_panel = null;
        this.account_open = false;
        return;
      }
      this.collapsed = !this.collapsed;
      if (this.collapsed) {
        this.account_open = false;
      }
    },
    open_modal(id) {
      this.mobile_panel = null;
      nb.modal.open(id);
    },
    toggle_mobile_panel(panel) {
      this.mobile_panel = this.mobile_panel === panel ? null : panel;
      this.account_open = false;
    },
    close_dropdowns() {
      this.account_open = false;
      this.mobile_panel = null;
    },
  }));
};

const alpine_media_insert = function () {
  Alpine.data("media_insert", () => ({
    caret: null,
    caret_pos: 0,
    hide_save_button: true,
    mode: "insert",
    insert_doc_html() {
      return nb.populate_template("nb_media_insert_doc_" + this.embed_info.doc.insert_mode + "_tpl", {
        uuid: this.file_info.uuid,
        name: this.file_info.name,
        title: this.file_info.title || this.file_info.name,
        description:
          this.file_info.description ||
          (this.file_info.size ? this.file_info.size.fileSize(1) : ""),
      });
    },
    insert_vid_html() {
      return nb.populate_template("nb_media_insert_vid_tpl", {
        uuid: this.file_info.uuid,
        type: "video/" + this.vid_type(),
        width: this.file_info.width,
        height: this.file_info.height,
      });
    },
    insert_svg_html() {
      return nb.populate_template("nb_media_insert_svg_tpl", {
        src: nb.base_url + "/img/" + this.file_info.uuid,
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
      const tpl_name =
        img_aspect >= 1.0
          ? "nb_media_insert_img_landscape_tpl"
          : "nb_media_insert_img_portrait_tpl";
      return nb.populate_template(tpl_name, {
        uuid: this.file_info.uuid,
        width: this.file_info.width,
        height: this.file_info.height,
        sizes: media_sizes.join(", "),
        src: src,
        srcset: srcset.join(", "),
      });
    },
    set_media() {
      if (this._file_info_changed()) {
        nb.api.put(nb.base_url + "/api/v1/.files_meta/" + this.file_info.uuid, {
          title: this.file_info.title,
          description: this.file_info.description,
        });
      }

      nb.modal.close("nb-modal-insert-media");
      nb.media_modal._set_media(nb.media_modal.field, this.file_info);
    },
    insert_media() {
      if (this._file_info_changed()) {
        nb.api.put(nb.base_url + "/api/v1/.files_meta/" + this.file_info.uuid, {
          title: this.file_info.title,
          description: this.file_info.description,
        });
      }

      if (!nb.edit.active_editor) {
        nb.modal.close("nb-modal-insert-media");
        return;
      }

      let html = "";
      const file_type = this.file_type();
      if (file_type === "img") {
        html = this.insert_img_html();
      } else if (file_type === "doc") {
        html = this.insert_doc_html();
      } else if (file_type === "vid") {
        html = this.insert_vid_html();
      } else if (file_type === "svg") {
        html = this.insert_svg_html();
      }

      nb.edit.restore_caret_pos();
      nb.edit.insert_html(html);
      nb.modal.close("nb-modal-insert-media");
    },
    embed_media() {
      if (!this.embed_info || !this.embed_info.active) {
        return;
      }
      if (this.mode === "select_embed") {
        let value = null;
        if (this.embed_info.active === "vimeo" && this.embed_info.vimeo.id) {
          value = "vimeo-" + this.embed_info.vimeo.id;
          if (this.embed_info.vimeo.hash) {
            value += ":" + this.embed_info.vimeo.hash;
          }
        } else if (this.embed_info.active === "youtube" && this.embed_info.youtube.id) {
          value = "youtube-" + this.embed_info.youtube.id;
        }
        if (value) {
          nb.media_modal._set_media(nb.media_modal.field, { uuid: value });
          nb.modal.close("nb-modal-insert-media");
        }
        return;
      }
      const embed_data_el = document.querySelector(
        "#media_modal_embed_options div[data-nb-embed=" +
          this.embed_info.active +
          "]"
      );
      if (embed_data_el) {
        nb.edit.restore_caret_pos();
        const html = embed_data_el.innerHTML
          .trim()
          .replaceAll(/:(\w+)="(.+?)"/g, "");
        nb.edit.insert_html(html);
      }
      nb.modal.close("nb-modal-insert-media");
    },
    ...nb.media_library,
  }));
};

const alpine_modal_settings = function () {
  Alpine.data("modal_settings", (page_id) => ({
    page_id: page_id,
    settings: {},

    select_image(field_name, field_ix = undefined) {
      nb.media_alpine.mode = "select";
      nb.media_alpine.filter(["img", "svg"]);
      nb.media_alpine.reset_tab();
      nb.media_modal.me = this;
      nb.media_modal._set_media = this._set_media;
      nb.media_modal.field = field_name;
      nb.media_modal.field_ix = field_ix;
      nb.modal.open("nb-modal-insert-media");
    },
    _set_media(field_name, field_data) {
      const ix = Number(nb.media_modal.field_ix);
      if (Number.isInteger(ix)) {
        nb.media_modal.me.settings[field_name][ix] = field_data.uuid;
      } else {
        nb.media_modal.me.settings[field_name] = field_data.uuid;
      }
    },
    delete_image(field_name, field_ix = undefined) {
      this.settings[field_name] = "";
    },

    move_item(field_name, old_ix, new_ix) {
      if (
        old_ix === new_ix ||
        new_ix < 0 ||
        new_ix >= this.settings[field_name].length
      ) {
        return;
      }
      var value = this.settings[field_name].splice(old_ix, 1)[0];
      this.settings[field_name].splice(new_ix, 0, value);
    },

    save() {
      nb.api
        .put(nb.base_url + "/api/v1/.config/" + this.page_id, this.settings)
        .then((data) => {
          if (data.success) {
            nb.notify(nb.text.saved);
            nb.modal.close("nb-modal-settings");
          } else {
            nb.notify(data.message);
          }
        });
    },
  }));
};

document.addEventListener("alpine:init", () => {
  alpine_nimblybar();
  alpine_media_insert();
  alpine_modal_settings();
});

window.addEventListener("DOMContentLoaded", () => {
  nb_bar_init_edit_controls();
});
