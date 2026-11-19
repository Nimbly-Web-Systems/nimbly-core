<label class="my-6 flex cursor-pointer items-center gap-3">
  <input type="checkbox" name="published" class="toggle toggle-success"
    x-init="settings.published = [#if page_published=true echo=true echo_else=false#]" x-model="settings.published">
  <span class="text-sm text-neutral-700">[#text Published#]</span>
</label>
