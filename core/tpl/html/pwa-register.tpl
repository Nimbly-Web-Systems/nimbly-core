<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('[#base-url#]/service-worker.js?v=[#app-modified#]', {
            scope: '[#base-url#]/'
        });
    });
}
</script>
