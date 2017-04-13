FROM alpine

RUN apk add --no-cache bash perl python3 && \
    pip3 install --upgrade pip setuptools httpie && \
    rm -r /root/.cache

COPY ./vcr /vcr
COPY ./docker-entrypoint.sh /

ENTRYPOINT ["/docker-entrypoint.sh"]
