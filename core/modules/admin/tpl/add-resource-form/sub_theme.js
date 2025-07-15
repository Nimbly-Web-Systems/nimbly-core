document.addEventListener("alpine:init", () => {
  Alpine.data("sub_theme", (fd) => ({
    subthemes: [],
    init() {
      this.set_subthemes(fd.recovery_theme);
      this.$watch(
        () => fd.recovery_theme,
        (val) => {
          this.set_subthemes(val);
        }
      );
    },
    set_subthemes(theme_id) {
      const theme = __themes[theme_id];
      const new_subs =
        theme && theme.subthemes && theme.subthemes[_lang]
          ? theme.subthemes[_lang].split(",").map((s) => s.trim())
          : [];
      
      this.subthemes = new_subs;
      fd.sub_theme = Array.isArray(fd.sub_theme)
        ? fd.sub_theme.filter((sub) => new_subs.includes(sub))
        : [];
    },
  }));
});
