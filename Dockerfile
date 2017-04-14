FROM openresty/openresty:alpine-fat

RUN apk add --no-cache bash perl python3 && \
    pip3 install --upgrade pip setuptools httpie && \
    rm -r /root/.cache

RUN luarocks install moonscript

COPY conf/nginx.conf /usr/local/openresty/nginx/conf/nginx.conf
COPY conf/app.lua /usr/local/openresty/nginx/app.lua
COPY ./tests /tests

ENTRYPOINT ["/usr/local/openresty/bin/openresty", "-g", "daemon off;"]
