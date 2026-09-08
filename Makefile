# -- Inspired by ---------------------------------------------------------------
# http://fabien.potencier.org/symfony4-best-practices.html
# https://speakerdeck.com/mykiwi/outils-pour-ameliorer-la-vie-des-developpeurs-symfony?slide=47
# https://blog.theodo.fr/2018/05/why-you-need-a-makefile-on-your-project/
# Aligné sur ~/Work/secret-santa

# Setup ------------------------------------------------------------------------

SYMFONY       = symfony
PHP           = php
COMPOSER      = composer
CONSOLE       = php bin/console
DOCKER        = docker
PHPUNIT       = ./vendor/bin/phpunit

# Docker MySQL test DB (never use the main "santa" database)
TEST_DATABASE_URL ?= mysql://root:root@127.0.0.1:6950/santa_test?serverVersion=8.0&charset=utf8mb4

DOMAIN        = santa
INSTANCE      ?= staging

.DEFAULT_GOAL = help
.PHONY: help start stop clean status info docker-up docker-stop composer-install serve unserve proxy cc permissions fixtures migrate create-admin test-db test test-unit test-functional test-integration test-coverage ci deploy-staging deploy-production rollback-staging rollback-prod

## -- 🐝 The Symfony Makefile 🐝 -----------------------------------
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## -- Project 🚀  --------------------------------------------------------
start: docker-up serve proxy composer-install permissions info ## Start stack + Symfony server

stop: unserve docker-stop ## Stop Symfony binary server and Docker

clean: stop ## Remove containers, vendors, var
	$(DOCKER) compose --env-file .env.local down --remove-orphans
	rm -rf vendor var .phpunit.cache .phpunit.result.cache

status:
	$(SYMFONY) server:status

info:
	@echo ----------------------------------------------------------------------------
	@echo PhpMyAdmin UI - Manage/View MySql datas     :     http://127.0.0.1:6951
	@echo ----------------------------------------------------------------------------
	@echo Mailhog UI    - Email smtp for tests        :     http://127.0.0.1:6953
	@echo ----------------------------------------------------------------------------
	@echo Santa UI        - Application local domain    :     https://$(DOMAIN).wip
	@echo ----------------------------------------------------------------------------

## -- Docker 🐳 ----------------------------------------------------------------
docker-up: ## Start MySQL, PhpMyAdmin, Mailhog
	@docker network inspect santa_santa_network >/dev/null 2>&1 || docker network create --driver bridge --subnet 10.209.0.0/24 santa_santa_network
	$(DOCKER) compose --env-file .env.local up -d
	@echo "Waiting for MySQL..."
	@for i in $$(seq 1 30); do \
		$(DOCKER) compose --env-file .env.local exec -T database mysqladmin ping -h127.0.0.1 -uroot -p"$$(grep MYSQL_ROOT_PASSWORD .env.local | cut -d= -f2-)" --silent && break; \
		sleep 2; \
	done

docker-stop: ## Stop the docker hub
	$(DOCKER) compose --env-file .env.local stop

## -- Composer 🧙‍♂️ ------------------------------------------------------------
composer-install: ## Install vendors
	$(COMPOSER) install

## -- Symfony  ---------------------------------------------------------------
serve: ## Serve the application with HTTPS support
	$(SYMFONY) server:start --daemon --allow-all-ip

unserve: ## Stop the webserver
	$(SYMFONY) server:stop

proxy: ## Start and attach local domain proxy on symfony server
	$(SYMFONY) proxy:start
	$(SYMFONY) proxy:domain:attach $(DOMAIN)

cc: ## Clear the cache
	$(CONSOLE) c:c

permissions: ## Fix permissions of all var files
	chmod -R 777 var 2>/dev/null || true

migrate: ## Create DB if needed and run migrations
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

fixtures: ## Reset schema (dev)
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:schema:drop --force --full-database --no-interaction
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) c:c

create-admin: ## Create admin - make create-admin EMAIL=a@b.c PASSWORD=secret
	$(CONSOLE) app:create-admin $(EMAIL) $(PASSWORD)

## -- Tests ✨ ------------------------------------------------------
test-db: ## Ensure test database exists on Docker MySQL
	DATABASE_URL='$(TEST_DATABASE_URL)' APP_ENV=test $(CONSOLE) doctrine:database:drop --force --if-exists || true
	DATABASE_URL='$(TEST_DATABASE_URL)' APP_ENV=test $(CONSOLE) doctrine:database:create --if-not-exists
	DATABASE_URL='$(TEST_DATABASE_URL)' APP_ENV=test $(CONSOLE) doctrine:schema:drop --force --full-database --no-interaction || true
	DATABASE_URL='$(TEST_DATABASE_URL)' APP_ENV=test $(CONSOLE) doctrine:schema:create --no-interaction || \
		(DATABASE_URL='$(TEST_DATABASE_URL)' APP_ENV=test $(CONSOLE) doctrine:schema:drop --force --full-database --no-interaction && \
		 DATABASE_URL='$(TEST_DATABASE_URL)' APP_ENV=test $(CONSOLE) doctrine:schema:create --no-interaction)

test: test-db ## Run all PHPUnit suites
	DATABASE_URL='$(TEST_DATABASE_URL)' $(PHP) $(PHPUNIT)

test-unit: ## Unit tests only
	$(PHP) $(PHPUNIT) --testsuite Unit

test-functional: test-db ## Functional tests
	DATABASE_URL='$(TEST_DATABASE_URL)' $(PHP) $(PHPUNIT) --testsuite Functional

test-integration: test-db ## Integration tests
	DATABASE_URL='$(TEST_DATABASE_URL)' $(PHP) $(PHPUNIT) --testsuite Integration

test-coverage: test-db ## Generate coverage in var/coverage - MUST be 100%
	@rm -rf var/coverage
	@mkdir -p var/coverage
	XDEBUG_MODE=coverage DATABASE_URL='$(TEST_DATABASE_URL)' $(PHP) $(PHPUNIT) \
		--coverage-html=var/coverage/html \
		--coverage-clover=var/coverage/clover.xml \
		--coverage-text
	$(PHP) bin/check-coverage var/coverage/clover.xml
	@echo ""
	@echo "Dashboard HTML : $(CURDIR)/var/coverage/html/index.html"
	@echo "Clover XML     : $(CURDIR)/var/coverage/clover.xml"
	@echo "Ouvrir         : xdg-open var/coverage/html/index.html"

## -- CI ✨ ------------------------------------------------------------
ci: ## Local equivalent of GitHub Actions checks (needs MySQL test DB)
	$(COMPOSER) validate --strict --no-check-publish
	$(CONSOLE) lint:yaml config translations --parse-tags
	$(CONSOLE) lint:twig templates
	$(CONSOLE) lint:container
	$(MAKE) test

## -- Deploy ✨ ------------------------------------------------------
deploy-staging:
	vendor/bin/dep deploy staging -vv

deploy-production:
	vendor/bin/dep deploy production -vv

rollback-staging: ## Rollback STAGING
	$(PHP) ./vendor/bin/dep rollback staging -vvv

rollback-prod: ## Rollback PRODUCTION
	$(PHP) ./vendor/bin/dep rollback production -vvv
