FROM openresty/openresty:alpine

RUN apk add --no-cache bash perl python3 && \
    pip3 install --upgrade pip setuptools httpie && \
    rm -r /root/.cache

COPY ./conf/* /etc/nginx/
COPY ./tests /tests

ENTRYPOINT ["/usr/local/openresty/bin/openresty", "-g", "daemon off;"]
