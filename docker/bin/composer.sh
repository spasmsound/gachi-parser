#!/bin/bash

DIRNAME="$( cd "$( dirname "$0" )" && pwd )"

docker-compose -p bhs exec php bash -c "composer $1"