#!/usr/bin/env bash

DIRNAME="$( cd "$( dirname "$0" )" && pwd )"
source "${DIRNAME}/helpers.sh"

CMD="bin/console ${1}"

compose exec php $CMD
