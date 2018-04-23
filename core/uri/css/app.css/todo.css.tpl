body { 
    font-family: sans-serif; 
}

img {
    max-width: 100%;
    height: auto;
}

/* nb-table */

table.nb-table  {
    border-spacing: 0;
    width: 100%;
}

table.nb-table td, table.nb-table th {
    border-bottom: 1px solid #ddd;
    padding: 12px 15px;
    text-align: left;
}

table.nb-table td:first-child, table.nb-table th:first-child { 
    padding-left: 0; 
}

table.nb-table td:last-child, table.nb-table th:last-child { 
    padding-right: 0; 
}

/* nb-container */

.nb-container {
    margin: 0 auto;
    max-width: 1240px;
    position: relative;
    width: 100%;
    box-sizing: border-box;
    padding: 20px;
}

.nb-container * { 
    box-sizing: border-box; 
}

.nb-container.medium { 
    max-width: 640px; 
}

.nb-container.large { 
    max-width: 960px; 
}

/* nb-callout */

.nb-callout {
    padding: 10px;
    border-left: 3px solid [primary-color];
    margin-bottom: 20px;
    background-color: #eee;
}

.nb-callout a[data-close] {
    text-decoration: none;
    cursor: pointer
}

/* nb-notification */

.nb-notification {
    position: fixed;
    top: 40px;
    right: 5px;
    text-align: right;
    padding: 0 10px;
    background: [primary-color];
    font-size: 14px;
    line-height: 20px;
    border-radius: 4px;
}

.nb-notification p {
    margin: 0 15px 0 0;
    color: #fff;
}

.nb-notification a { 
    color: #fff; 
}

/* page regions */

#top-bar-fixed {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 36px;
    background: [primary-color];
    z-index: 1000;
}

#top-bar-fixed + div, #top-bar-fixed + section, #top-bar-fixed + .nb-container { 
    margin-top: 36px; 
}

#off-left-panel, #off-right-panel {
    position: fixed;
    z-index: 1;
    top: 0px;
    left: 0;
    bottom: 0;
    background-color: [primary-color];
    overflow-x: hidden;
    transition: width 0.3s;
    transition-timing-function: ease-in-out;
    width: 0;
}

.user #off-left-panel, .user #off-right-panel {
    top: 36px;
}

#off-right-panel {
    right: 0;
    left: auto;
}

#off-left-panel.nb-open, #off-right-panel.nb-open { 
    width: 300px; 
}

#off-left-panel.nb-close, #off-right-panel.nb-close  { 
    display: inherit; 
}

#page-wrapper {
    position: relative;
    width: 100%;
    overflow: hidden;
}

#page {
    transition: margin-left 0.3s;
    transition-timing-function: ease-in-out;
    width: 100%;
}

#page-wrapper.push-right #page {
    margin-left: 300px;
}

/* modal dialog */

#modal {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0,0,0,0.6);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

#modal.nb-close {
    display: none;
}

#modal-content {
    max-width: 680px;
    min-width: 480px;
    background: white;
    height: 100%;
    padding: 50px 10px 10px 10px;
    position: relative;
    box-sizing: border-box;
}

.small-screen #modal-content {
    min-width: 100%;
}

#modal-content .icon-close {
    position: absolute;
    top: 0;
    right: 0;
    padding: 10px;
    font-size: 20px;
    line-height: 20px;
}

#modal-content .modal-caption {
    position: absolute;
    top: 0;
    right: 0;
    left: 0;
    font-size: 15px;
    line-height: 40px;
    text-align: center;
    text-transform: uppercase;
}

#modal-content .modal-content {
    overflow-y: auto;
    height: 100%;
}

/* nb-button */

.nb-button {
    background-color: [primary-color];
    border: 1px solid [primary-color];
    border-radius: 4px;
    color: #fff;
    cursor: pointer;
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    height: 36px;
    line-height: 36px;
    padding: 0 20px;
    text-align: center;
    text-decoration: none;
    text-transform: uppercase;
    white-space: nowrap;
    margin-bottom: 10px;
}

.nb-button:focus, .nb-button:hover {
    filter: brightness(95%);
    outline: 0;
}

.nb-button-icon {
    padding: 0 10px;
    line-height: 34px;
    border-radius: 0;
    height: 34px;
}

#top-bar-fixed .nb-button-icon {
    background-color: transparent;
    border: 1px solid transparent;
}

.nb-button-icon.active { border-bottom: 3px solid [secondary-color]; }

#top-bar-fixed .nb-button-icon.active { border-bottom: 3px solid [secondary-color]; }


.nb-button[disabled], .nb-button[disabled]:focus, .nb-button[disabled]:hover {
    cursor: default;
    filter: grayscale(100%);
    opacity: .5;
}

.nb-button-outline, .nb-button-outline:focus, .nb-button-outline:hover {
    background-color: transparent;
    color: [primary-color];
}

.nb-button-outline:focus, .nb-button-outline:hover {
    filter: brightness(80%);
}

.nb-button-outline[disabled]:focus, .nb-button.button-outline[disabled]:hover {
    filter: grayscale(100%);
    opacity: .5;
}

.nb-button-clear {
    background-color: transparent;
    border-color: transparent;
    color: [primary-color];
}

.nb-button-clear:focus, .nb-button-clear:hover {
    filter: brightness(80%);
}

.nb-button-clear[disabled]:focus, .nb-button-clear[disabled]:hover { color: #333; }


/* nb-form */

.nb-form .form-alert { color: red; }

.nb-form input[type='email'], .nb-form input[type='number'], .nb-form input[type='password'], 
.nb-form input[type='search'], .nb-form input[type='tel'], .nb-form input[type='text'], 
.nb-form input[type='url'], .nb-form textarea, .nb-form select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-color: transparent;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-shadow: none;
    box-sizing: inherit;
    height: 36px;
    padding: 6px 10px;
    width: 100%;
    margin-bottom: 10px;
}

.nb-form input[type='email']:focus, .nb-form input[type='number']:focus, 
.nb-form input[type='password']:focus, .nb-form input[type='search']:focus, 
.nb-form input[type='tel']:focus, .nb-form input[type='text']:focus, .nb-form input[type='url']:focus, 
.nb-form textarea:focus, .nb-form select:focus {
    border-color: [primary-color];
    outline: 0;
}

.nb-form select {
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" height="14" viewBox="0 0 29 14" width="29"><path fill="#d1d1d1" d="M9.37727 3.625l5.08154 6.93523L19.54036 3.625"/></svg>') center right no-repeat;
    padding-right: 30px;
}

.nb-form select:focus { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" height="14" viewBox="0 0 29 14" width="29"><path fill="#9b4dca" d="M9.37727 3.625l5.08154 6.93523L19.54036 3.625"/></svg>'); }

.nb-form textarea { min-height: 65px; }

.nb-form label, .nb-form legend {
    display: block;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 5px;
}

.nb-form fieldset {
    border-width: 0;
    padding: 0;
}

.nb-form input[type='checkbox'], .nb-form input[type='radio'] { display: inline; }

.nb-form .label-inline {
    display: inline-block;
    font-weight: normal;
    margin-left: 5px;
}

/* nb-img-grid */ 

.nb-img-grid {
    line-height: 0;
    -webkit-column-count: 3;
    -webkit-column-gap: 10px;
    -moz-column-count: 3;
    -moz-column-gap: 10px;
    column-count: 3;
    column-gap: 10px;
    text-align: center;
}

.nb-img-grid img { margin-bottom: 10px; }

.small-screen .nb-img-grid {
    -webkit-column-count: 2;
    -moz-column-count: 2;
    column-count: 2;
}

/* nb-menu */

ul.nb-menu {
    margin-bottom: 0;
    border-bottom: 1px solid rgba(0,0,0,0.20);
    padding: 0;
}

ul.nb-menu.light { border-bottom: 1px solid rgba(255,255,255,0.20); }

ul.nb-menu li {
    list-style-type: none;
    margin: 0;
}

ul.nb-menu li a, ul.nb-menu li.caption {
    display: block;
    width: 100%;
    line-height: 30px;
    padding: 10px 20px;
    text-decoration: none;
}

ul.nb-menu.light li a {
    color: white;
    background: [primary-color];
}

ul.nb-menu.horizontal { border-bottom: 0; }

ul.nb-menu.horizontal li { display: inline-block; }

ul.nb-menu.horizontal li:last-child a { padding-right: 0; }

ul.nb-menu.horizontal li:first-child a { padding-left: 0; }

.icon-nav, .icon-gear, .icon-edit {
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' x='0px' y='0px' width='30px' height='30px' viewBox='0 0 30 30' enable-background='new 0 0 30 30' xml:space='preserve'><rect width='30' height='4'/><rect y='24' width='30' height='4'/><rect y='12' width='30' height='4'/></svg>");
    background-size: contain;
    width: 20px;
    height: 20px;
    display: inline-block;
    line-height: 36px;
    vertical-align: middle;
}

.icon-gear {
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24'><path d='M20 13.44v-2.88l-1.8-.3c-.1-.397-.3-.794-.6-1.39l1.1-1.49-2.1-2.088-1.5 1.093c-.5-.298-1-.497-1.4-.596L13.5 4h-2.9l-.3 1.79c-.5.098-.9.297-1.4.595L7.4 5.292 5.3 7.38l1 1.49c-.3.496-.4.894-.6 1.39l-1.7.2v2.882l1.8.298c.1.497.3.894.6 1.39l-1 1.492 2.1 2.087 1.5-1c.4.2.9.395 1.4.594l.3 1.79h3l.3-1.79c.5-.1.9-.298 1.4-.596l1.5 1.092 2.1-2.08-1.1-1.49c.3-.496.5-.993.6-1.39l1.5-.3zm-8 1.492c-1.7 0-3-1.292-3-2.982 0-1.69 1.3-2.98 3-2.98s3 1.29 3 2.98-1.3 2.982-3 2.982z'/></svg>");
    width: 34px;
    height: 34px;
}

.icon-edit {
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' x='0px' y='0px' viewBox='0 0 1000 1000' enable-background='new 0 0 1000 1000' xml:space='preserve'><g><path d='M500,10C229.4,10,10,229.4,10,500c0,270.6,219.4,490,490,490c270.6,0,490-219.4,490-490C990,229.4,770.6,10,500,10z M344.2,767.1H232.9V655.8l328-328l111.3,111.3L344.2,767.1z M758.2,353.1L703.3,408L592,296.7l54.9-54.9c11.9-11.9,29.7-11.9,41.5,0l69.8,69.8C770.1,323.4,770.1,341.2,758.2,353.1z'/></g></svg>");
}

[data-close], [data-open], [data-toggle], [data-delete], [data-post], [data-put], [data-get] { cursor: pointer; }

/* lazy image loading fx */




/* helpers */

.float-left { float: left; }

.float-right { float: right; }

.scroll-h { overflow-x: auto; }

.nb-open { display: inherit; }

.nb-close , div.nb-close, li.nb-close, ul.nb-close { display: none; }

.medium-screen .only-sm, .large-screen .only-sm { display: none; }

.small-screen .only-md, .large-screen .only-md { display: none; }

.small-screen .only-lg, .medium-screen .only-lg { display: none; }

.large-screen .only-lg { display: inherit; }

.medium-screen .only-md { display: inherit; }

.small-screen .only-sm { display: inherit; }

.small { font-size: 10px; }

.align-right { text-align: right; }

.align-center, .center { text-align: center; }

.align-left { text-align: left; }

.align-justify { text-align: justify; }

.nb-40 { width: 40%!important; }
