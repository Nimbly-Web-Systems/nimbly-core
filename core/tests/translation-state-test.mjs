import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";

let alpine_callback;
let form_edit_factory;

globalThis.document = {
  addEventListener: (_name, callback) => {
    alpine_callback = callback;
  },
};
globalThis.Alpine = {
  data: (_name, factory) => {
    form_edit_factory = factory;
  },
};
globalThis.nb = { forms: {} };
globalThis._initial_lang = "nl";
globalThis._ai_record_action_fields = ["location_name", "title", "body"];
globalThis._translation_languages = ["nl", "en"];

await import("../modules/admin/tpl/edit-resource-form/form_edit.js");
alpine_callback();

const state = form_edit_factory("articles", "test-record");
state.form_data = {
  location_name: { nl: "Locatie", en: "" },
  title: { nl: "Titel", en: "Title" },
  body: { nl: "<p>Inhoud</p>", en: "<p>Body</p>" },
};
state.initialize_translation_empty();

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

const edit_template = await readFile(
  new URL("../modules/admin/tpl/edit-resource-form/index.tpl", import.meta.url),
  "utf8",
);
assert.match(edit_template, /data\.ai_record_action_fields type=json empty=\[\]/);
assert.match(edit_template, /data\.translation_languages type=json empty=\[\]/);

console.log("Translation state tests passed");
