#!/bin/bash

DIRNAME="$( cd "$( dirname "$0" )" && pwd )"
source "${DIRNAME}/helpers.sh"

compose up -d --remove-orphans --force-recreate
