<section class="bg-neutral-100 p-3 sm:p-4 md:p-6 lg:p-8 font-primary">
    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]<span aria-hidden="true">/</span><span class="text-neutral-700">[#text Navigation#]</span>
    </nav>
    <h1 class="text-2xl font-semibold text-neutral-800 md:text-3xl">[#text Navigation#]</h1>
    <p class="mt-2 max-w-2xl text-sm text-neutral-600">[#text Arrange links independently from their destination pages. Use the arrow controls from the keyboard or drag items with a pointer.#]</p>

    <form method="get" class="mt-6 flex flex-wrap items-end gap-3">
        <label class="form-control"><span class="label-text">[#text Slot#]</span>
            <select class="select select-bordered" name="slot" onchange="this.form.submit()"
                x-data='{ options: [#get navigation_editor_slots_json echo#] }' x-init="$el.value = '[#navigation_editor_slot#]'">
                <template x-for="option in options" :key="option.value"><option :value="option.value" x-text="option.label"></option></template>
            </select>
        </label>
        <label class="form-control"><span class="label-text">[#text Language#]</span>
            <select class="select select-bordered" name="language" onchange="this.form.submit()"
                x-data='{ options: [#get navigation_editor_languages_json echo#] }' x-init="$el.value = '[#navigation_editor_language#]'">
                <template x-for="option in options" :key="option.value"><option :value="option.value" x-text="option.label"></option></template>
            </select>
        </label>
    </form>

    <p class="alert alert-success mt-4 [#if navigation_editor_notice=(empty) echo=hidden#]">[#get navigation_editor_notice echo#]</p>
    <p class="alert alert-error mt-4 [#if navigation_editor_error=(empty) echo=hidden#]">[#get navigation_editor_error echo#]</p>

    <form method="post" id="navigation-editor" class="mt-6 rounded-box border border-base-300 bg-base-100 p-4">
        [#form-key navigation#]
        <input type="hidden" name="slot" value="[#navigation_editor_slot#]">
        <input type="hidden" name="language" value="[#navigation_editor_language#]">
        <input type="hidden" name="revision" value="[#navigation_editor_revision#]">
        <input type="hidden" name="items" id="navigation-editor-items">
        <div id="navigation-editor-tree" class="space-y-2"></div>
        <div class="mt-4 flex gap-2">
            <button class="btn btn-sm" type="button" id="navigation-add">[#text Add link#]</button>
            <button class="btn btn-primary btn-sm" type="submit">[#text Save navigation#]</button>
        </div>
    </form>
</section>
<script>
(() => {
    const form = document.getElementById('navigation-editor');
    if (!form) return;
    let items = [#get navigation_editor_items_json default=[]#];
    const pages = [#get navigation_editor_pages_json default={}#];
    const maxDepth = [#navigation_editor_depth#];
    const root = document.getElementById('navigation-editor-tree');
    const uid = () => 'item-' + Math.random().toString(36).slice(2, 10);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const walk = (list, id, parent = null) => {
        for (let i = 0; i < list.length; i++) {
            if (list[i].id === id) return { list, i, item: list[i], parent };
            const found = walk(list[i].children || [], id, list[i]);
            if (found) return found;
        }
        return null;
    };
    const depth = id => {
        const find = (list, level) => { for (const item of list) { if (item.id === id) return level; const d = find(item.children || [], level + 1); if (d) return d; } return 0; };
        return find(items, 1);
    };
    const targetInput = item => item.target.kind === 'page'
        ? `<select data-field="value" class="select select-bordered select-sm ${pages[item.target.value] ? '' : 'select-error'}">${pages[item.target.value] ? '' : `<option value="${esc(item.target.value)}" selected>[#text Missing page#]: ${esc(item.target.value)}</option>`}${Object.entries(pages).map(([id,title]) => `<option value="${esc(id)}" ${item.target.value===id?'selected':''}>${esc(title)}</option>`).join('')}</select>`
        : item.target.kind === 'group' ? '' : `<input data-field="value" class="input input-bordered input-sm min-w-52" value="${esc(item.target.value)}" placeholder="${item.target.kind === 'external_url' ? 'https://…' : 'nl/path'}">`;
    function renderList(list) {
        return `<ol class="space-y-2">${list.map(item => `<li draggable="true" data-id="${esc(item.id)}" class="rounded border border-base-300 bg-base-100 p-2">
            <div class="flex flex-wrap items-center gap-2">
                <span class="cursor-grab select-none text-neutral-400" aria-hidden="true">↕</span>
                <input data-field="label" class="input input-bordered input-sm min-w-40 flex-1" value="${esc(item.label)}" aria-label="[#text Label#]">
                <select data-field="kind" class="select select-bordered select-sm" aria-label="Destination type">
                    ${[['internal_url','[#text Internal URL#]'],['page','[#text Page#]'],['external_url','[#text External URL#]'],['group','[#text Group label#]']].map(([v,l]) => `<option value="${v}" ${item.target.kind===v?'selected':''}>${l}</option>`).join('')}
                </select>
                ${targetInput(item)}
                <button type="button" class="btn btn-square btn-ghost btn-sm" data-action="up" aria-label="[#text Move up#]">↑</button>
                <button type="button" class="btn btn-square btn-ghost btn-sm" data-action="down" aria-label="[#text Move down#]">↓</button>
                <button type="button" class="btn btn-square btn-ghost btn-sm" data-action="out" aria-label="[#text Move one level out#]">←</button>
                <button type="button" class="btn btn-square btn-ghost btn-sm" data-action="in" aria-label="[#text Move under previous item#]">→</button>
                <button type="button" class="btn btn-square btn-ghost btn-sm text-error" data-action="delete" aria-label="[#text Delete#]">×</button>
            </div>${item.children?.length ? `<div class="ml-8 mt-2">${renderList(item.children)}</div>` : ''}
        </li>`).join('')}</ol>`;
    }
    function render() { root.innerHTML = items.length ? renderList(items) : '<p class="text-sm text-neutral-500">[#text No links yet.#]</p>'; }
    root.addEventListener('input', e => { const row=e.target.closest('[data-id]'); const found=row&&walk(items,row.dataset.id); if(!found)return; if(e.target.dataset.field==='label')found.item.label=e.target.value; if(e.target.dataset.field==='value')found.item.target.value=e.target.value; });
    root.addEventListener('change', e => { const row=e.target.closest('[data-id]'); const found=row&&walk(items,row.dataset.id); if(!found)return; if(e.target.dataset.field==='kind'){found.item.target={kind:e.target.value,value:e.target.value==='page'?(Object.keys(pages)[0]||''):''};render();} });
    root.addEventListener('click', e => { const button=e.target.closest('[data-action]'); if(!button)return; const row=button.closest('[data-id]'); const found=walk(items,row.dataset.id); if(!found)return; const {list,i,item,parent}=found; const action=button.dataset.action;
        if(action==='delete') list.splice(i,1);
        if(action==='up'&&i>0) [list[i-1],list[i]]=[list[i],list[i-1]];
        if(action==='down'&&i<list.length-1) [list[i+1],list[i]]=[list[i],list[i+1]];
        if(action==='in'&&i>0&&depth(item.id)<maxDepth){list.splice(i,1);(list[i-1].children ||= []).push(item);}
        if(action==='out'&&parent){const parentFound=walk(items,parent.id);list.splice(i,1);parentFound.list.splice(parentFound.i+1,0,item);}
        render();
    });
    let dragged=null;
    root.addEventListener('dragstart', e => { const row=e.target.closest('[data-id]'); dragged=row?.dataset.id||null; });
    root.addEventListener('dragover', e => { if(e.target.closest('[data-id]'))e.preventDefault(); });
    root.addEventListener('drop', e => { e.preventDefault(); const target=e.target.closest('[data-id]')?.dataset.id; if(!dragged||!target||dragged===target)return; const from=walk(items,dragged),to=walk(items,target); if(!from||!to)return; from.list.splice(from.i,1); const updated=walk(items,target); updated.list.splice(updated.i,0,from.item);render(); });
    document.getElementById('navigation-add').addEventListener('click', () => { items.push({id:uid(),label:'[#text New link#]',target:{kind:'internal_url',value:''},children:[]});render(); });
    form.addEventListener('submit', () => { document.getElementById('navigation-editor-items').value=JSON.stringify(items); });
    render();
})();
</script>
