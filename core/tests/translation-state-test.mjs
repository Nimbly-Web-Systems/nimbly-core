import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";

globalThis.nb = { base_url: "/jereis", forms: {} };
globalThis._initial_lang = "nl";
globalThis._translation_mode = "field";
globalThis._ai_record_action_fields = ["location_name", "title", "body"];
globalThis._translation_languages = ["nl", "en"];
globalThis._resource_url = "";

await import("../modules/forms/lib/build-form/edit-form-state.js");

const state = globalThis.nb_build_form_edit_state("articles", "test-record");
state.form_data = {
  location_name: { nl: "Locatie", en: "" },
  title: { nl: "Titel", en: "Title" },
  body: { nl: "<p>Inhoud</p>", en: "<p>Body</p>" },
};
state.initialize_translation_empty();

assert.equal(
  state.editor_html_for_display('<img src="/img/12345678901234567890/1200w">'),
  '<img src="/jereis/img/12345678901234567890/1200w">',
);
assert.equal(
  state.editor_html_for_display('<video src="/download/12345678901234567890"></video>'),
  '<video src="/jereis/download/12345678901234567890"></video>',
);
assert.equal(
  state.editor_html_for_storage('<img src="/jereis/img/12345678901234567890/1200w">'),
  '<img src="/img/12345678901234567890/1200w">',
);

assert.equal(state.has_empty_translation_fields("nl"), false);
assert.equal(state.has_empty_translation_fields("en"), true);
assert.equal(state.translation_field_empty("unknown", "en"), false);
assert.equal(state.translation_value_is_empty("<p><br></p>"), true);
assert.equal(state.translation_value_is_empty('<p><img src="image.jpg"></p>'), false);

state.sync_ai_input({
  target: {
    name: "location_name",
    value: "Location",
    closest: () => null,
  },
}, "en");

assert.equal(state.form_data.location_name.en, "Location");
assert.equal(state.has_empty_translation_fields("en"), false);

state.sync_ai_input({
  target: {
    name: "location_name",
    value: "",
    closest: () => null,
  },
}, "en");

assert.equal(state.translation_field_empty("location_name", "en"), true);
assert.equal(state.has_empty_translation_fields("en"), true);

state.sync_ai_editor({
  target: { dataset: { nbEdit: "body" } },
  detail: { value: "<p><br></p>" },
}, "en");
assert.equal(state.translation_field_empty("body", "en"), true);

// edit_submit() must strip the get_resource_record_sc()-added lang/translations
// bookkeeping before persisting, in "field" (new-way) i18n mode — otherwise it
// leaks into the stored record on every save.
{
  let put_payload = null;
  globalThis.nb.api = {
    put: (_url, payload) => {
      put_payload = payload;
      return Promise.resolve({ success: true });
    },
  };
  globalThis.nb.text = { record_updated: "Updated" };
  globalThis.nb.edit = { get_field_values: () => ({}) };
  globalThis.nb.system_message = () => Promise.resolve();
  globalThis.document = { referrer: "" };
  globalThis.window = { location: { href: "" } };

  const submit_state = globalThis.nb_build_form_edit_state("articles", "test-record");
  submit_state.form_data = { title: { nl: "Titel", en: "Title" }, lang: "nl", translations: { nl: true } };
  submit_state.redirect_on_submit = false;
  await submit_state.edit_submit();

  assert.equal("lang" in put_payload, false, "lang bookkeeping key leaked into PUT payload");
  assert.equal("translations" in put_payload, false, "translations bookkeeping key leaked into PUT payload");
  assert.equal(put_payload.title.en, "Title");
}

const build_form_script = await readFile(
  new URL("../modules/forms/lib/build-form/fscript.js", import.meta.url),
  "utf8",
);
assert.match(build_form_script, /data\.ai_record_action_fields type=json empty=\[\]/);
assert.match(build_form_script, /data\.translation_languages type=json empty=\[\]/);
assert.match(build_form_script, /edit-form-state\.js/);

console.log("Translation state tests passed");
