<section class="space-y-3" x-data='{
        languages: [#fmt var=managed_page_preview_languages type=json empty=[]#],
        default_language: "[#get managed_page_preview_default_language#]",
        active_language() {
            const form_language = this.$store.form_language.current;
            return this.languages.some((language) => language.code === form_language)
                ? form_language
                : this.default_language;
        },
        current() {
            return this.languages.find((language) => language.code === this.active_language()) || {};
        }
    }'>
    <h3 class="font-semibold text-neutral-800">[#text Page actions#]</h3>
    <a class="btn btn-sm w-full" :href="current().preview_url" target="_blank" rel="noopener">
        [#text Open preview#] <span class="uppercase" x-text="active_language()"></span>
    </a>
</section>
