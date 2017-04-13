#!/usr/bin/env bash

find ./vcr -name "*.test" | while read -r file; do
    cmd=`head -n 1 $file`
    case=$(basename $file .test)
    echo $cmd
    eval $cmd > ./actual
    tail -n +2 $file > ./expected
    diff --ignore-all-space ./actual ./expected
done
