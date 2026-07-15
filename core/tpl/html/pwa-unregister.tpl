<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        const scope = new URL('[#base-url#]/', window.location.origin).href;
        const registrations = await navigator.serviceWorker.getRegistrations();
        await Promise.all(registrations
            .filter((registration) => registration.scope === scope)
            .map((registration) => registration.unregister()));

        if ('caches' in window) {
            const scopeKey = new URL(scope).pathname
                .replace(/[^a-z0-9]+/gi, '-')
                .replace(/^-+|-+$/g, '') || 'root';
            const cachePrefix = `nimbly-static-${scopeKey}-`;
            const keys = await caches.keys();
            await Promise.all(keys
                .filter((key) => key.startsWith(cachePrefix))
                .map((key) => caches.delete(key)));
        }
    });
}
</script>
