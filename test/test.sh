#!/usr/bin/env bash
set -ex

go run ./src/main.go &
TASK_PID=$!

sleep 1

http --proxy=http:http://localhost:9999 -v PUT httpbin.org/anything time=$(date +%s)
exit=$?

kill $TASK_PID

exit $exit