CWD = $(shell pwd)

.EXPORT_ALL_VARIABLES:

default: test

phptest:
	@docker run -it --rm -v $(CWD):/home/circleci/source cimg/php:$(VERSION) \
		bash -c 'set -ex; cp -R ~/source/. ./; composer --quiet install; \
		composer -n phpcs' > results-$(VERSION).txt

.PHONY: php74

php74:
	$(MAKE) phptest VERSION=7.4

.PHONY: php80

php80:
	$(MAKE) phptest VERSION=8.0

.PHONY: php81

php81:
	$(MAKE) phptest VERSION=8.1

.PHONY: test

test:
	$(MAKE) -j 3 php74 php80 php81