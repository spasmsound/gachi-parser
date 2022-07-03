#!/bin/bash

DIRNAME="$( cd "$( dirname "$0" )" && pwd )"

docker-compose -p bhs exec php bash -c " php -d memory_limit=-1 vendor/bin/phpstan analyse src $1"