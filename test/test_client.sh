#!/usr/bin/env bash
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