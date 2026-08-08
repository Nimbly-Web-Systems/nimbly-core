import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";

globalThis.nb = { base_url: "/jereis", forms: {} };

await import("../modules/forms/lib/build-form/edit-form-state.js");

const base_config = {
  initial_lang: "nl",
  translation_mode: "field",
  ai_record_action_fields: ["location_name", "title", "body"],
  translation_languages: ["nl", "en"],
  resource_url: "",
  redirect_on_success: false,
};

const state = globalThis.nb_build_form_edit_state("articles", "test-record", base_config);
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
  },
}, "en");

assert.equal(state.form_data.location_name.en, "Location");
assert.equal(state.has_empty_translation_fields("en"), false);

state.sync_ai_input({
  target: {
    name: "location_name",
    value: "",
  },
}, "en");

assert.equal(state.translation_field_empty("location_name", "en"), true);
assert.equal(state.has_empty_translation_fields("en"), true);

state.sync_ai_editor({
  target: { dataset: { nbEdit: "body" } },
  detail: { value: "<p><br></p>" },
}, "en");
assert.equal(state.translation_field_empty("body", "en"), true);

function install_submit_environment(put) {
  const notifications = [];
  globalThis.nb.api = { put };
  globalThis.nb.text = { record_updated: "Updated" };
  globalThis.nb.edit = { get_field_values: () => ({}) };
  globalThis.nb.notify = (message) => notifications.push(message);
  globalThis.nb.system_message = () => Promise.resolve();
  globalThis.document = { referrer: "" };
  globalThis.window = { location: { href: "" } };
  return notifications;
}

// Rendering-only i18n bookkeeping and password UI state must never leak into
// the persisted record. Keeping a password must also omit the decrypted value.
{
  let put_payload = null;
  const notifications = install_submit_environment((_url, payload) => {
    put_payload = payload;
    return Promise.resolve({ success: true });
  });
  const submit_state = globalThis.nb_build_form_edit_state("articles", "test-record", base_config);
  submit_state.form_data = {
    title: { nl: "Titel", en: "Title" },
    lang: "nl",
    translations: { nl: true },
    password: "decrypted-current-password",
    keep_password: true,
  };
  await submit_state.edit_submit();

  assert.equal("lang" in put_payload, false, "lang bookkeeping key leaked into PUT payload");
  assert.equal("translations" in put_payload, false, "translations bookkeeping key leaked into PUT payload");
  assert.equal("keep_password" in put_payload, false, "password UI state leaked into PUT payload");
  assert.equal("password" in put_payload, false, "current password was unnecessarily submitted");
  assert.equal(put_payload.title.en, "Title");
  assert.deepEqual(notifications, ["Updated"]);
  assert.equal(globalThis.window.location.href, "");
}

// Explicit replacement keeps the new password but still strips UI state.
{
  let put_payload = null;
  install_submit_environment((_url, payload) => {
    put_payload = payload;
    return Promise.resolve({ success: true });
  });
  const submit_state = globalThis.nb_build_form_edit_state("users", "test-user", base_config);
  submit_state.form_data = { password: "replacement-password", keep_password: false };
  await submit_state.edit_submit();

  assert.equal(put_payload.password, "replacement-password");
  assert.equal("keep_password" in put_payload, false);
}

// Redirect behavior and all other rendered configuration stay local to each
// state instance instead of being read from mutable page globals.
{
  install_submit_environment(() => Promise.resolve({ success: true }));
  globalThis.document.referrer = "/nb-admin/test-records";
  const first = globalThis.nb_build_form_edit_state("first", "one", {
    ...base_config,
    initial_lang: "en",
    record: { title: "First" },
    resource_url: "/nb-admin/first",
    redirect_on_success: true,
  });
  const second = globalThis.nb_build_form_edit_state("second", "two", {
    ...base_config,
    initial_lang: "nl",
    record: { title: "Second" },
    resource_url: "/nb-admin/second",
    redirect_on_success: false,
  });
  for (const form_state of [first, second]) {
    form_state.$watch = () => {};
    form_state.$nextTick = (callback) => callback();
    form_state.$el = null;
    form_state.init_edit_state();
  }

  assert.equal(first.lang, "en");
  assert.equal(first.form_data.title, "First");
  assert.equal(second.lang, "nl");
  assert.equal(second.form_data.title, "Second");

  first.form_data = { title: "Saved" };
  await first.edit_submit();
  assert.equal(globalThis.window.location.href, "/nb-admin/first");
}

// Network failures restore the interactive state and produce feedback.
{
  const notifications = install_submit_environment(() => Promise.reject(new Error("Network unavailable")));
  const submit_state = globalThis.nb_build_form_edit_state("articles", "test-record", base_config);
  submit_state.form_data = { title: "Test" };
  await submit_state.edit_submit();
  assert.equal(submit_state.busy, false);
  assert.deepEqual(notifications, ["Network unavailable"]);
}

const build_form_script = await readFile(
  new URL("../modules/forms/lib/build-form/fscript.js", import.meta.url),
  "utf8",
);
assert.match(build_form_script, /const form_config =/);
assert.match(build_form_script, /nb_build_form_edit_state\(resource_id, record_id, form_config\)/);
assert.doesNotMatch(build_form_script, /var _initial_lang|var _frecord|var _i18n_fields/);

console.log("Translation state tests passed");
