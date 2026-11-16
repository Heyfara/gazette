# Commandes du quotidien. Tout passe par Docker (compose.yaml).
DC = docker compose
PHP = $(DC) run --rm -T gazette
NODE = $(DC) run --rm -T --no-deps node

.PHONY: help install up down test test-php test-front lint build sh

help: ## Liste des commandes
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  %-12s %s\n", $$1, $$2}'

install: ## Installe les dépendances PHP et npm
	$(PHP) composer install
	$(NODE) npm ci

up: ## Lance le dev : front sur http://localhost:5174, API via nginx sur :8090
	$(DC) up -d --build
	@echo "→ http://localhost:5174"

down: ## Arrête le dev
	$(DC) down

test: test-php test-front ## Tous les tests

test-php: ## Tests PHP (phpunit) ; filtrer : make test-php ARGS="--filter Prompt"
	$(PHP) vendor/bin/phpunit $(ARGS)

test-front: ## Tests front (vitest) et vérification des types
	$(NODE) sh -c "npm run typecheck && npm test -- $(ARGS)"

lint: ## Vérifie le conteneur Symfony et les YAML
	$(PHP) bin/console lint:container
	$(PHP) bin/console lint:yaml config

build: ## Construit l'image de prod en local
	docker build --target prod -t gazette:local .

sh: ## Shell dans le container PHP
	$(DC) run --rm gazette sh
