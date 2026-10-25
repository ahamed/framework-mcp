.PHONY: help up down restart init ssl-cert example-install example-update library-install wp logs shell clean test

PLUGIN_DIR := /var/www/html/wp-content/plugins/framework-mcp-example
LIBRARY_DIR := /var/www/html/framework-mcp-library

help:
	@echo "framework-mcp playground commands:"
	@echo "  make up               Start all services in the background (generates SSL cert if needed)"
	@echo "  make example-install  Composer install for the example plugin (links this package)"
	@echo "  make init             Install WordPress + MCP Adapter, activate the example, issue an app password"
	@echo "  make library-install  Composer install for this package (repo root composer.json)"
	@echo "  make test             Run the package's unit tests inside the php container"
	@echo "  make wp CMD=\"...\"     Run a WP-CLI command (e.g. CMD=\"plugin list\")"
	@echo "  make logs             Tail php and nginx logs"
	@echo "  make shell            Open a bash shell in the php container"
	@echo "  make down             Stop all services"
	@echo "  make clean            Stop all services and remove volumes"

ssl-cert:
	chmod +x docker/scripts/generate-ssl-cert.sh
	./docker/scripts/generate-ssl-cert.sh

up: ssl-cert
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

init:
	docker compose run --rm wordpress-init
	docker compose up -d --force-recreate php

example-install:
	docker compose run --rm php composer install --working-dir=$(PLUGIN_DIR)

example-update:
	docker compose run --rm php composer update --working-dir=$(PLUGIN_DIR)

library-install:
	docker compose run --rm php composer install --working-dir=$(LIBRARY_DIR)

wp:
	docker compose run --rm php wp --allow-root $(CMD)

logs:
	docker compose logs -f php nginx

shell:
	docker compose exec php bash

test:
	docker compose exec php bash -lc 'cd $(LIBRARY_DIR) && composer install --no-interaction && composer test'

clean:
	docker compose down -v
