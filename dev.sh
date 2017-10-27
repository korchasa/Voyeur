#!/usr/bin/env bash

build_container() {
    docker build -t korchasa/voyeur .
}

test_travis() {
    docker run -v $(pwd):/project --rm skandyla/travis-cli lint .travis.yml
}

test_cluster() {
    docker-compose run sender --body --ignore-stdin PUT receiver/anything foo=bar
}

run_cluster() {
    docker-compose down
    build_container
    docker-compose up --remove-orphans -d
}

main() {
  set -eo pipefail; [[ "$TRACE" ]] && set -x
  declare cmd="$1"
  set -ex
  case "$cmd" in
    build_container)  shift; build_container "$@";;
    test_cluster)  shift; test_cluster "$@";;
    test_travis)  shift; test_travis "$@";;
    run_cluster) shift; run_cluster "$@";;
    *) help "$@";;
  esac
}

help() {
    echo -e "test_cluster                     Test"
}

main "$@"