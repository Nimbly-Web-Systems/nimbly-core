[#feature-cond features="manage-[#resource-id#],delete-[#resource-id#],(any)-[#resource-id#]" tpl="action_delete"#]
[#feature-cond features="manage-[#resource-id#],edit-[#resource-id#],(any)-[#resource-id#]" tpl="action_edit"#]
<template x-if="record._action_url">
    [#action_url#]
</template>
