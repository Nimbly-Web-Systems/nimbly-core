import Alpine from 'alpinejs';

const nb_breakpoints = {};

nb_breakpoints.init = function () {
    Alpine.store('breakpoints_store', {
        width: window.innerWidth,
        init() {
            window.addEventListener('resize', () => {
                this.width = window.innerWidth;
            });
        },
        is_below(bp) { 
             return this.width < window.nb.tw_breakpoints[bp]; 
        },
        is_above(bp) { // actually is_above_or_equal
            return this.width >= window.nb.tw_breakpoints[bp]; 
        },   
    });
    window.nb.breakpoints = Alpine.store('breakpoints_store');
};

export default nb_breakpoints;
