# 1. build css, js and text files

FROM registry.access.redhat.com/ubi9/php-82:latest AS build

USER 0

ADD docker/docker-nimbly-openshift/empty_file ext/theme.css
ADD docker/docker-nimbly-openshift/empty_file ext/data/.i18n/_.po
ADD core/modules/install/uri/install/tailwind.theme.js ext/tailwind.theme.js
ADD tailwind.config.js .
ADD package.json .
ADD js js
ADD css css
ADD core core
ADD ext ext

RUN chown -R 1001:0 .
USER 1001

RUN npm install
RUN npm run build

# 2. setup app server

FROM registry.access.redhat.com/ubi9/php-82:latest AS deploy

ADD core core
ADD ext ext
ADD docker/docker-nimbly-openshift/php.ini /etc/
COPY --from=build /opt/app-root/src/ext/static/app.css /opt/app-root/src/ext/static/app.css
COPY --from=build /opt/app-root/src/ext/static/app.js /opt/app-root/src/ext/static/app.js
COPY --from=build /opt/app-root/src/ext/static/tw-elements.umd.min.js /opt/app-root/src/ext/static/tw-elements.umd.min.js
COPY --from=build /opt/app-root/src/ext/static/medium-editor.min.js  /opt/app-root/src/ext/static/medium-editor.min.js 
COPY --from=build /opt/app-root/src/ext/data/.i18n/bundle.po /opt/app-root/src/ext/data/.i18n/bundle.po
ADD index.php .
ADD docker/docker-nimbly-openshift/.htaccess .

CMD /usr/libexec/s2i/run