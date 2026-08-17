function load_location_picker_script(src) {
  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
      if (window.L) {
        resolve();
      } else {
        existing.addEventListener("load", resolve, { once: true });
        existing.addEventListener("error", reject, { once: true });
      }
      return;
    }

    const script = document.createElement("script");
    script.src = src;
    script.addEventListener("load", resolve, { once: true });
    script.addEventListener("error", reject, { once: true });
    document.head.append(script);
  });
}

function load_location_picker_dependencies() {
  if (window.location_picker_dependencies) {
    return window.location_picker_dependencies;
  }

  if (!document.querySelector("link[data-location-picker-css]")) {
    const stylesheet = document.createElement("link");
    stylesheet.rel = "stylesheet";
    stylesheet.href = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
    stylesheet.dataset.locationPickerCss = "true";
    document.head.append(stylesheet);
  }

  window.location_picker_dependencies = window.L
    ? Promise.resolve()
    : load_location_picker_script("https://unpkg.com/leaflet@1.9.4/dist/leaflet.js");

  return window.location_picker_dependencies;
}

document.addEventListener("alpine:init", () => {
  Alpine.data("location_picker", (options) => ({
    latitude_field: options.latitude_field,
    longitude_field: options.longitude_field,
    map: null,
    marker: null,
    loading: true,
    load_error: false,

    async init() {
      try {
        await load_location_picker_dependencies();
        await this.$nextTick();

        const latitude = Number.parseFloat(this.form_data[this.latitude_field]);
        const longitude = Number.parseFloat(this.form_data[this.longitude_field]);
        const has_location = Number.isFinite(latitude) && Number.isFinite(longitude);

        this.map = L.map(this.$refs.map, {
          worldCopyJump: true,
        }).setView(has_location ? [latitude, longitude] : [20, 0], has_location ? 9 : 2);

        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
          maxZoom: 19,
          attribution: "&copy; OpenStreetMap contributors",
        }).addTo(this.map);

        if (has_location) {
          this.place_marker(latitude, longitude);
        }

        this.map.on("click", (event) => {
          this.set_location(event.latlng.lat, event.latlng.lng);
        });

        // form_data's initial value is read once, above — this covers
        // values that arrive afterward from outside the map's own click/drag
        // handlers (currently: action-import-document's AI-estimated
        // coordinates on the add form).
        this.$watch(`form_data.${this.latitude_field}`, () => this.sync_from_fields());
        this.$watch(`form_data.${this.longitude_field}`, () => this.sync_from_fields());

        this.loading = false;
        this.$nextTick(() => this.map.invalidateSize());
      } catch (error) {
        console.error("Location picker failed to initialize", error);
        this.loading = false;
        this.load_error = true;
      }
    },

    set_location(latitude, longitude) {
      const normalized_longitude = ((longitude + 180) % 360 + 360) % 360 - 180;
      this.form_data[this.latitude_field] = latitude.toFixed(6);
      this.form_data[this.longitude_field] = normalized_longitude.toFixed(6);
      this.place_marker(latitude, normalized_longitude);
    },

    // Only reads form_data (never writes it), so this can't loop back into
    // its own $watch triggers.
    sync_from_fields() {
      if (!this.map) {
        return;
      }
      const latitude = Number.parseFloat(this.form_data[this.latitude_field]);
      const longitude = Number.parseFloat(this.form_data[this.longitude_field]);
      if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return;
      }
      this.place_marker(latitude, longitude);
      this.map.setView([latitude, longitude], Math.max(this.map.getZoom(), 9));
    },

    place_marker(latitude, longitude) {
      if (!this.marker) {
        const marker_icon = L.divIcon({
          className: "",
          html: this.$refs.marker_icon.content.firstElementChild.cloneNode(true),
          iconSize: [48, 48],
          iconAnchor: [24, 48],
        });
        this.marker = L.marker([latitude, longitude], {
          draggable: true,
          autoPan: true,
          icon: marker_icon,
        }).addTo(this.map);
        this.marker.on("dragend", () => {
          const position = this.marker.getLatLng();
          this.set_location(position.lat, position.lng);
        });
        return;
      }

      this.marker.setLatLng([latitude, longitude]);
    },
  }));
});
