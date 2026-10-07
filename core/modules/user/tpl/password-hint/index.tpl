<p class="mt-1 text-sm" x-data="{ length: 0, refused: false }"
    x-init="$el.previousElementSibling.addEventListener('input', e => length = e.target.value.length); $el.previousElementSibling.addEventListener('invalid', () => refused = true)"
    :class="length >= 8 ? 'text-success' : (refused ? 'text-error' : 'text-neutral-500')">
    <span x-show="length >= 8" x-cloak aria-hidden="true">✓</span>
    [#text At least 8 characters#]
</p>
