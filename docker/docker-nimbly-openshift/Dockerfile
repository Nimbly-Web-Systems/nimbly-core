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

RUN npm install --no-audit --verbose
RUN npm run build

# 2. setup app server

FROM registry.access.redhat.com/ubi9/php-82:latest AS deploy

ADD docker/docker-nimbly-openshift/empty_file ext/data/.tmp/sessions/_.
ADD core core
ADD ext ext
ADD docker/docker-nimbly-openshift/php.ini /etc/
COPY --from=build /opt/app-root/src/ext/static/app.css /opt/app-root/src/ext/static/app.css
COPY --from=build /opt/app-root/src/ext/static/app.js /opt/app-root/src/ext/static/app.js
COPY --from=build /opt/app-root/src/ext/static/tw-elements.umd.min.js /opt/app-root/src/ext/static/tw-elements.umd.min.js
COPY --from=build /opt/app-root/src/ext/static/medium-editor.min.js  /opt/app-root/src/ext/static/medium-editor.min.js 
COPY --from=build /opt/app-root/src/ext/data/.i18n/bundle.po /opt/app-root/src/ext/data/.i18n/bundle.po
ADD docker/docker-nimbly-openshift/.htaccess .
ADD index.php .

USER 0
RUN find ext/data -type d -exec chmod 750 {} +
RUN chown -R 1001:0 ext/data
USER 1001

CMD /usr/libexec/s2i/run