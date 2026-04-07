.PHONY: build shell comp-install update stan cs-lint cs-fix compat-check test debug ci audit security

# ─── Configuration ─────────────────────────────────────────────────────────────
# Default PHP version used to build the Docker image and run all commands.
# Override on the fly without changing this file:
#   make <target> PHP_VERSION=7.4
#   make <target> PHP_VERSION=5.6
#   make <target> PHP_VERSION=8.2
PHP_VERSION ?= 7.2

DC      = docker compose
PHP     = PHP_VERSION=$(PHP_VERSION) $(DC) run --rm php
PHP_DBG = XDEBUG_MODE=debug PHP_VERSION=$(PHP_VERSION) $(DC) run --rm php

# ─── Docker ────────────────────────────────────────────────────────────────────

build:  ## Build (or rebuild) the Docker image for the current PHP_VERSION
	PHP_VERSION=$(PHP_VERSION) $(DC) build

shell:  ## Open an interactive shell in the PHP container
	$(PHP) bash

# ─── Composer ──────────────────────────────────────────────────────────────────

comp-install:  ## Install Composer dependencies
	$(PHP) composer install

update:  ## Update Composer dependencies
	$(PHP) composer update

install: build update shell
# ─── Quality ───────────────────────────────────────────────────────────────────

stan:  ## Run PHPStan static analysis
	$(PHP) vendor/bin/phpstan analyse --memory-limit=4G

cs-lint:  ## Check code style without making changes (dry-run)
	$(PHP) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --diff --dry-run

cs-fix:  ## Fix code style automatically
	$(PHP) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php

# PHP compatibility check: runs phpcs against src/ for a given PHP target version.
# The container always runs on the default PHP (8.2); only the *checked* version changes.
# Usage:
#   make compat-check                    # check for PHP 5.6 (default)
#   make compat-check COMPAT_VERSION=7.4
COMPAT_VERSION ?= 5.6
compat-check:  ## Check source compatibility with a specific PHP version (see COMPAT_VERSION)
	$(PHP) sh -c 'vendor/bin/phpcs --config-set installed_paths vendor/phpcompatibility/php-compatibility \
	    && vendor/bin/phpcs --colors -s ./src/ --standard=PHPCompatibility --runtime-set testVersion $(COMPAT_VERSION)'

# ─── Tests ─────────────────────────────────────────────────────────────────────

test:  ## Run PHPUnit tests
	$(PHP) vendor/bin/phpunit

# ─── Debug ─────────────────────────────────────────────────────────────────────

debug:  ## Open a shell with Xdebug step-debug enabled (XDEBUG_MODE=debug)
	$(PHP_DBG) bash

# ─── CI ────────────────────────────────────────────────────────────────────────

ci: stan cs-lint  ## Run all quality checks (PHPStan + code style)

# ─── Security ──────────────────────────────────────────────────────────────────

audit:  ## Run Composer security audit
	$(PHP) composer audit

security: audit
