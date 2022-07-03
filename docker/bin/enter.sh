#!/usr/bin/env bash

DIRNAME="$( cd "$( dirname "$0" )" && pwd )"
source "${DIRNAME}/helpers.sh"

COMMAND="/bin/bash"
service=$1

compose exec ${service} ${COMMAND}
