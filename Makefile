.PHONY: build up down shell install update test stan lint fix debug release

DC = docker compose
PHP = $(DC) run --rm php
PHP_DEBUG = XDEBUG_MODE=debug $(DC) run --rm php

## Docker
build:
	$(DC) build

up:
	$(DC) up -d

down:
	$(DC) down

shell:
	$(PHP) bash

## Composer
comp-install:
	$(PHP) composer install

update:
	$(PHP) composer update

## Quality
stan:
	$(PHP) vendor/bin/phpstan analyse --memory-limit=4G

cs-lint:
	$(PHP) vendor/bin/php-cs-fixer fix --diff --dry-run

cs-fix:
	$(PHP) vendor/bin/php-cs-fixer fix


## Debug (Xdebug step-debug enabled)
debug:
	$(PHP_DEBUG) bash

## CI (runs all checks)
ci: stan cs-lint

install: build update comp-install

audit:
	$(PHP) composer audit

security: audit
