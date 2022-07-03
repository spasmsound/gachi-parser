#!/bin/bash

DIRNAME="$( cd "$( dirname "$0" )" && pwd )"
source "${DIRNAME}/helpers.sh"

compose down
cp docker-compose.override.local.yml docker-compose.override.yml
cp .env.dist .env
compose build
compose up -d --remove-orphans --force-recreate
compose exec php composer install
compose exec php bin/console doctrine:database:drop --force
compose exec php bin/console doctrine:database:create
compose -p bhs exec php bin/console doctrine:migrations:migrate --no-interaction
compose -p bhs exec php bin/console doctrine:fixtures:load --no-interaction
compose exec php yarn
compose exec php yarn build
