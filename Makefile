TESTER_DIR ?= ../umami-site-template-test
TESTER_NAME ?= umami-site-template-test
RECIPE ?= umami

.PHONY: dev-sync-source dev-test-install

dev-sync-source:
	@mkdir -p "$(TESTER_DIR)/source"
	rsync -a --delete \
		--exclude='.git/' \
		--exclude='.ddev/' \
		--exclude='.DS_Store' \
		--exclude='vendor/' \
		--exclude='web/core/' \
		--exclude='web/modules/contrib/' \
		--exclude='web/themes/contrib/' \
		--exclude='web/sites/default/files/' \
		./ "$(TESTER_DIR)/source/"

dev-test-install:
	@mkdir -p "$(TESTER_DIR)"
	@if [ ! -f "$(TESTER_DIR)/.ddev/config.yaml" ]; then \
		cd "$(TESTER_DIR)" && ddev config --project-type=drupal11 --docroot=web --project-name="$(TESTER_NAME)"; \
	fi
	cd "$(TESTER_DIR)" && ddev start
	@if [ ! -f "$(TESTER_DIR)/composer.json" ]; then \
		cd "$(TESTER_DIR)" && ddev composer create-project drupal/cms .; \
	fi
	$(MAKE) dev-sync-source TESTER_DIR="$(TESTER_DIR)"
	cd "$(TESTER_DIR)" && ddev composer config repositories.umami path source
	cd "$(TESTER_DIR)" && ddev composer config repositories.umami_next path source/packages/umami_next
	cd "$(TESTER_DIR)" && ddev composer config repositories.umami_next_theme path source/packages/umami_next_theme
	cd "$(TESTER_DIR)" && ddev composer require drupal/umami:^1@dev drupal/umami_next:^1@dev drupal/umami_next_theme:^1@dev --with-all-dependencies
	cd "$(TESTER_DIR)" && ddev drush site:install "recipes/$(RECIPE)" --account-name=admin --account-pass=admin -y
	cd "$(TESTER_DIR)" && ddev drush cr
