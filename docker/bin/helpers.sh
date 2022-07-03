#!/usr/bin/env bash

function compose {
    local cmd="docker-compose -p parser"
    ${cmd} "$@"
}