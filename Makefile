.PHONY: help build up down restart logs shell migrate fresh seed optimize clean dev prod

# Default target
help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# Docker commands
build: ## Build Docker containers
	docker-compose build --no-cache

up: ## Start all services
	docker-compose up -d

down: ## Stop all services
	docker-compose down

restart: ## Restart all services
	docker-compose restart

logs: ## View logs from all services
	docker-compose logs -f

shell: ## Access application shell
	docker-compose exec app sh

# Database commands
migrate: ## Run database migrations
	docker-compose exec app php artisan migrate

fresh: ## Fresh database with migrations
	docker-compose exec app php artisan migrate:fresh

seed: ## Seed the database
	docker-compose exec app php artisan db:seed

# Application commands
optimize: ## Optimize application (cache config, routes, views)
	docker-compose exec app php artisan config:cache
	docker-compose exec app php artisan route:cache
	docker-compose exec app php artisan view:cache

key: ## Generate application key
	docker-compose exec app php artisan key:generate

clear: ## Clear all caches
	docker-compose exec app php artisan config:clear
	docker-compose exec app php artisan route:clear
	docker-compose exec app php artisan view:clear
	docker-compose exec app php artisan cache:clear

# Utility commands
clean: ## Remove all containers, volumes, and images
	docker-compose down -v --rmi all --remove-orphans
	docker system prune -f

dev: ## Start development environment
	docker-compose -f docker-compose.yml -f docker-compose.override.yml up -d

prod: ## Start production environment
	docker-compose -f docker-compose.yml up -d

setup: ## Initial setup with database migrations
	./docker/setup.sh

validate: ## Validate Docker setup and services
	./docker/validate.sh

# Testing commands
test: ## Run tests
	docker-compose exec app php artisan test

# Queue commands
queue-work: ## Start queue worker manually
	docker-compose exec app php artisan queue:work redis --queue=webhooks,default --sleep=3 --tries=3

queue-restart: ## Restart queue workers
	docker-compose exec app php artisan queue:restart

queue-failed: ## List failed jobs
	docker-compose exec app php artisan queue:failed

# Webhook commands
process-retries: ## Process webhook retries
	docker-compose exec app php artisan webhooks:process-retries

# Maintenance commands
maintenance-on: ## Put application in maintenance mode
	docker-compose exec app php artisan down

maintenance-off: ## Bring application out of maintenance mode
	docker-compose exec app php artisan up

# Backup commands
backup-db: ## Backup database
	docker-compose exec db pg_dump -U webhook_user webhook_management > backup_$(shell date +%Y%m%d_%H%M%S).sql

# Permission commands
fix-permissions: ## Fix storage permissions
	docker-compose exec app chown -R www-data:www-data /var/www/html/storage
	docker-compose exec app chown -R www-data:www-data /var/www/html/bootstrap/cache
	docker-compose exec app chmod -R 775 /var/www/html/storage
	docker-compose exec app chmod -R 775 /var/www/html/bootstrap/cache
