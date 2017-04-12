#!/usr/bin/env bash
set -e

find /tests -name "*.test" | while read -r file; do
    actual=`$(head -n 1 $file)`
    echo $actual > ./actual
    tail -n +2 $file > ./expected
    diff ./actual ./expected
done