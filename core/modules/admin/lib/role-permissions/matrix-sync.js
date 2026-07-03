// Builds the final comma-separated "features" string for a role: checked
// checkboxes (collapsing a row's operations into its "manage-<resource>"
// token when Manage is checked) plus any custom tokens, or just "(all)"
// when that override is on.
window.nb_role_permissions_payload = function (form) {
    const all_checkbox = form.querySelector("input[name='features[]'][value='(all)']");
    if (all_checkbox && all_checkbox.checked) {
        return "(all)";
    }

    const tokens = [];

    form.querySelectorAll("[data-permission-row]").forEach((row) => {
        const manage = row.querySelector("[data-permission-manage]");
        const operations = [...row.querySelectorAll("[data-permission-operation]")];
        if (manage && manage.checked) {
            tokens.push(manage.value);
        } else {
            operations.forEach((input) => { if (input.checked) tokens.push(input.value); });
        }
    });

    form.querySelectorAll("input[name='features[]']:checked").forEach((input) => {
        if (input.value === "(all)" || input.closest("[data-permission-row]")) return;
        tokens.push(input.value);
    });

    const custom = (form.querySelector("[name=custom_features]")?.value || "")
        .split(/[\n,]+/).map((token) => token.trim()).filter(Boolean);

    return [...tokens, ...custom].join(",");
};

(() => {
    const form = document.currentScript.closest("form");

    form.querySelectorAll("[data-permission-row]").forEach((row) => {
        const manage = row.querySelector("[data-permission-manage]");
        const operations = [...row.querySelectorAll("[data-permission-operation]")];
        if (!manage || operations.length === 0) return;
        const sync_manage = () => manage.checked = operations.every((input) => input.checked);
        manage.addEventListener("change", () => operations.forEach((input) => input.checked = manage.checked));
        operations.forEach((input) => input.addEventListener("change", sync_manage));
        sync_manage();
    });

    const all_checkbox = form.querySelector("input[name='features[]'][value='(all)']");
    const extra = form.querySelector("[data-permission-extra]");
    if (all_checkbox && extra) {
        const sync_all = () => {
            const is_all = all_checkbox.checked;
            extra.hidden = is_all;
            extra.querySelectorAll("input, textarea").forEach((el) => el.disabled = is_all);
        };
        all_checkbox.addEventListener("change", sync_all);
        sync_all();
    }
})();
