<div x-init="form_data['[#_f.key#]'] = '[#_f.value#]'"
    class="border border-base-300 rounded-lg bg-white divide-y divide-base-200 overflow-hidden focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-neutral-400">
    [#if _f.resource=(not-empty) tpl=resource-options-check#]
    [#if _f.options=(not-empty) tpl=inline-options-check#]
</div>
