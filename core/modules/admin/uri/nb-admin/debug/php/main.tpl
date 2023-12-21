<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary flex justify-between">
    <div>
        <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800 ">[#text Debug#]</h1>
        <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">
            [#text PHP info.#]
        </h3>
    </div>
    <div>
        <a class="[#btn-class-secondary#]" href="[#base-url#]/nb-admin/debug">[#text Nimbly variables#]</a>
    </div>
</section>

<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">

    <style>
        #phpinfo table {
            border-collapse: collapse;
            border: 0;
            width: 934px;
        }

        #phpinfo .center {
            text-align: center;
        }

        #phpinfo .center table {
            margin: 1em auto;
            text-align: left;
        }

        #phpinfo .center th {
            text-align: center !important;
        }

        #phpinfo td,
        #phpinfo th {
            border: 1px solid #666;
            font-size: 75%;
            vertical-align: baseline;
            padding: 4px 5px;
        }

        #phpinfo th {
            position: sticky;
            top: 0;
            background: inherit;
        }

        #phpinfo h1 {
            font-size: 150%;
        }

        #phpinfo h2 {
            font-size: 125%;
        }

        #phpinfo h2 a:link,
        #phpinfo h2 a:visited {
            color: inherit;
            background: inherit;
        }

        #phpinfo .p {
            text-align: left;
        }

        #phpinfo .e {
            background-color: #ccf;
            width: 300px;
            font-weight: bold;
        }

        #phpinfo .h {
            background-color: #99c;
            font-weight: bold;
        }

        #phpinfo .v {
            background-color: #ddd;
            max-width: 300px;
            overflow-x: auto;
            word-wrap: break-word;
        }

        #phpinfo .v i {
            color: #999;
        }

        #phpinfo img {
            float: right;
            border: 0;
        }

        #phpinfo hr {
            width: 934px;
            background-color: #ccc;
            border: 0;
            height: 1px;
        }

        :root {
            --php-dark-grey: #333;
            --php-dark-blue: #4F5B93;
            --php-medium-blue: #8892BF;
            --php-light-blue: #E2E4EF;
            --php-accent-purple: #793862
        }

    </style>

    <div id="phpinfo" class="text-neutral-800 pt-4 bg-neutral-50 rounded-2xl shadow">
        [#debug php#]
    </div>
</section>