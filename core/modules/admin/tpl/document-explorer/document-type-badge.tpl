<span class="badge badge-xs font-semibold uppercase"
    :class="{ 'badge-error': family(doc) === 'pdf', 'badge-info': family(doc) === 'text', 'badge-success': family(doc) === 'sheet', 'badge-warning': family(doc) === 'slides', 'badge-secondary': family(doc) === 'design', 'badge-neutral': family(doc) === '' }"
    x-text="extension(doc)"></span>
