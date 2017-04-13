# Makefile configuration
.DEFAULT_GOAL := help
.PHONY: test all_tests

all_tests: ## Runs tests
	docker-compose down --remove-orphans && docker-compose up --build

test: ## Runs tests
	docker-compose up --build -d app && \
	docker-compose exec app http GET http://voyeur-httpbin.org/headers --json --proxy=http:http://localhost:80 --verbose && \
	docker-compose logs app

help:
	@grep --extended-regexp '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'
