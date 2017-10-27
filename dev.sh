#!/usr/bin/env bash
set -e

test_travis() {
    docker run -v $(pwd):/project --rm skandyla/travis-cli lint .travis.yml
}

client_loop() {
    while true
    do
        clear
        http --proxy=http:http://localhost:9999 -v GET httpbin.org/anything
        sleep 5
        http --proxy=http:http://localhost:9999 -v POST httpbin.org/anything/$(date +%s) API-Key:$(openssl rand -hex 4) hello=world
        sleep 5
        http --proxy=http:http://localhost:9999 -v PUT httpbin.org/anything time=$(date +%s)
        sleep 5
    done
}

test_proxy() {
    set -e
    $1 &
    TASK_PID=$!

    sleep 1

    http --body --ignore-stdin PUT httpbin.org/anything foo=bar > /tmp/expected.txt
    http --body --ignore-stdin --proxy=http:http://localhost:8080 PUT httpbin.org/anything foo=bar > /tmp/actual.txt
    exit=$?

    kill $TASK_PID

    if [[ $exit != 0 ]]; then
        exit 1
    fi

    git diff --no-index --ignore-space-at-eol --text --exit-code /tmp/expected.txt /tmp/actual.txt

    exit $?
}

main() {
  set -eo pipefail; [[ "$TRACE" ]] && set -x
  declare cmd="$1"
  case "$cmd" in
    client_loop) shift; client_loop "$@";;
    test_proxy)  shift; test_proxy "$@";;
    test_travis)  shift; test_travis "$@";;
    *) help "$@";;
  esac
}

help() {
    echo -e "test_proxy                     Test"
}

main "$@"