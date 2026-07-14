.PHONY: help dev setup setup-fresh test smoke-check console-audit migrate-check ufg-install deploy-package pilot-demo frontend cursor-setup

help:
	@echo "SOR — dev lokalny (bez Dockera)"
	@echo ""
	@echo "  make setup          Import bazy z deploy/ + migracje + UFG (po: sudo mysql < scripts/mysql-bootstrap.sql)"
	@echo "  make setup-fresh    Reset bazy i ponowny import dumpu"
	@echo "  make dev            composer dev (serve + queue + Vite)"
	@echo "  make frontend       npm run build → public/vite-dist/"
	@echo "  make smoke-check    HTTP + panel + API (scripts/smoke-manual-check.php)"
	@echo "  make console-audit  Audyt błędów konsoli admin+pilot (Playwright)"
	@echo "  make migrate-check  Sprawdź oczekujące migracje po git pull"
	@echo "  make ufg-install    Instalacja modułu UFG na istniejącej bazie"
	@echo "  make deploy-package Paczka ZIP do wdrożenia na serwer"
	@echo "  make pilot-demo     Migracje + konto testowe pilota + przypisanie imprez"
	@echo "  make cursor-setup   Konfiguracja MCP + Playwright dla Cursora"
	@echo ""
	@echo "Panel: http://127.0.0.1:8000/admin"
	@echo "Pilot: http://127.0.0.1:8000/pilot/login  (make pilot-demo → pilot@test.local / pilot123)"
	@echo "Dokumentacja: docs/DEV.md"

dev:
	composer dev

setup:
	@chmod +x scripts/setup-dev.sh
	@./scripts/setup-dev.sh

setup-fresh:
	@chmod +x scripts/setup-dev.sh
	@./scripts/setup-dev.sh --fresh

test:
	composer test

smoke-check:
	@php scripts/smoke-manual-check.php

console-audit:
	php artisan app:console-audit-bootstrap
	php artisan app:console-audit-urls
	node scripts/console-audit/run.mjs

migrate-check:
	php artisan app:migrate-check

ufg-install:
	php artisan ufg:install

deploy-package:
	@chmod +x scripts/build_deploy_package.sh
	@./scripts/build_deploy_package.sh

pilot-demo:
	php artisan pilot:setup-demo --migrate

frontend:
	npm run build

cursor-setup:
	@chmod +x scripts/cursor-setup.sh
	@./scripts/cursor-setup.sh
