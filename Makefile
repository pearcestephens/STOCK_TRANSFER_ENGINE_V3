# Stock Transfer Engine V3 - Development Commands

.PHONY: help install build test clean start stop restart logs shell

# Default target
help:
	@echo "Stock Transfer Engine V3 - Available Commands:"
	@echo ""
	@echo "Development:"
	@echo "  install     - Install all dependencies"
	@echo "  build       - Build the application"
	@echo "  test        - Run all tests"
	@echo "  lint        - Run linting checks"
	@echo "  format      - Format code"
	@echo ""
	@echo "Docker Operations:"
	@echo "  start       - Start all services with docker-compose"
	@echo "  stop        - Stop all services"
	@echo "  restart     - Restart all services"
	@echo "  logs        - View service logs"
	@echo "  shell       - Open shell in backend container"
	@echo ""
	@echo "Database:"
	@echo "  db-init     - Initialize database with sample data"
	@echo "  db-reset    - Reset database (WARNING: destroys all data)"
	@echo "  db-backup   - Create database backup"
	@echo ""
	@echo "Deployment:"
	@echo "  deploy      - Deploy to production"
	@echo "  clean       - Clean up build artifacts"

# Development Commands
install:
	@echo "Installing backend dependencies..."
	pip install -r requirements.txt
	@echo "Installing frontend dependencies..."
	cd frontend && npm install
	@echo "Dependencies installed successfully!"

build:
	@echo "Building backend..."
	python -m compileall backend/
	@echo "Building frontend..."
	cd frontend && npm run build
	@echo "Build completed successfully!"

test:
	@echo "Running backend tests..."
	cd backend && python -m pytest ../tests/backend/ -v
	@echo "Running frontend tests..."
	cd frontend && npm test
	@echo "All tests completed!"

lint:
	@echo "Linting backend code..."
	flake8 backend/
	black --check backend/
	isort --check-only backend/
	@echo "Linting frontend code..."
	cd frontend && npm run lint
	@echo "Linting completed!"

format:
	@echo "Formatting backend code..."
	black backend/
	isort backend/
	@echo "Formatting frontend code..."
	cd frontend && npm run format
	@echo "Code formatting completed!"

# Docker Operations
start:
	@echo "Starting Stock Transfer Engine V3..."
	docker-compose up -d
	@echo "Services started! Access the application at:"
	@echo "  Frontend: http://localhost:3000"
	@echo "  Backend API: http://localhost:8000"
	@echo "  API Documentation: http://localhost:8000/docs"

stop:
	@echo "Stopping all services..."
	docker-compose down
	@echo "Services stopped!"

restart:
	@echo "Restarting services..."
	docker-compose down
	docker-compose up -d
	@echo "Services restarted!"

logs:
	docker-compose logs -f

shell:
	docker-compose exec backend /bin/bash

# Database Operations
db-init:
	@echo "Initializing database..."
	docker-compose exec postgres psql -U stockuser -d stock_transfer_engine -f /docker-entrypoint-initdb.d/init.sql
	@echo "Database initialized!"

db-reset:
	@echo "WARNING: This will destroy all data!"
	@read -p "Are you sure? [y/N] " -n 1 -r; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker-compose down -v; \
		docker-compose up -d postgres redis; \
		sleep 5; \
		make db-init; \
	fi

db-backup:
	@echo "Creating database backup..."
	docker-compose exec postgres pg_dump -U stockuser stock_transfer_engine > backup_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "Backup created!"

# Deployment
deploy:
	@echo "Deploying to production..."
	docker-compose -f docker-compose.prod.yml up -d --build
	@echo "Deployment completed!"

clean:
	@echo "Cleaning up..."
	docker-compose down --rmi all --volumes --remove-orphans
	docker system prune -f
	rm -rf frontend/dist/
	rm -rf frontend/node_modules/
	find . -type d -name "__pycache__" -exec rm -rf {} +
	find . -type f -name "*.pyc" -delete
	@echo "Cleanup completed!"

# Development helpers
dev-backend:
	cd backend && python -m uvicorn app.main:app --reload --host 0.0.0.0 --port 8000

dev-frontend:
	cd frontend && npm run dev

dev:
	@echo "Starting development servers..."
	@echo "Backend will be available at http://localhost:8000"
	@echo "Frontend will be available at http://localhost:3000"
	@echo "Press Ctrl+C to stop both servers"
	@make -j2 dev-backend dev-frontend

# Quick setup for new developers
setup:
	@echo "Setting up Stock Transfer Engine V3 for development..."
	cp .env.example .env
	make install
	make start
	sleep 10
	make db-init
	@echo ""
	@echo "Setup completed! You can now access:"
	@echo "  Application: http://localhost:3000"
	@echo "  API: http://localhost:8000"
	@echo "  API Docs: http://localhost:8000/docs"
	@echo ""
	@echo "Default login credentials:"
	@echo "  Username: admin | Password: admin123"
	@echo "  Username: manager | Password: manager123"