[#feature-cond features="delete-[#resource-id#]" tpl="action_delete"#]
[#feature-cond features="edit-[#resource-id#]" tpl="action_edit"#]
<template x-if="record._action_url">
    [#action_url#]
</template>
