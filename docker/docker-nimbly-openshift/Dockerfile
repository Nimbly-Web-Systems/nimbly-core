# 1. build css, js and text files

FROM registry.access.redhat.com/ubi9/php-82:latest

ADD docker/docker-nimbly-openshift/empty_file ext/theme.css
ADD docker/docker-nimbly-openshift/empty_file ext/data/.i18n/_.po
ADD core/modules/install/uri/install/tailwind.theme.js ext/tailwind.theme.js
ADD package.json .
ADD js js
ADD css css
ADD core core
ADD ext ext

RUN npm install
RUN npm run build

# 2. setup app server

FROM registry.access.redhat.com/ubi9/php-82:latest

ADD core core
ADD ext ext
ADD index.php .
ADD docker/docker-nimbly-openshift/.htaccess .
ADD docker/docker-nimbly-openshift/php_env.ini .s2i/environment

CMD /usr/libexec/s2i/run