TESTER_DIR ?= ../umami-site-template-test
TESTER_NAME ?= umami-site-template-test
RECIPE ?= umami

.PHONY: dev-normalize-ddev-config dev-sync-source dev-test-install

# Drupal CMS scaffolding can rewrite DDEV config during create-project. Keep the
# reusable tester stable on the DDEV version used by this workspace.
dev-normalize-ddev-config:
	@config="$(TESTER_DIR)/.ddev/config.yaml"; \
	if [ -f "$$config" ]; then \
		if grep -q '^name:' "$$config"; then \
			perl -0pi -e 's/^name:.*$$/name: $(TESTER_NAME)/m' "$$config"; \
		else \
			awk 'NR == 1 { print; print "name: $(TESTER_NAME)"; next } { print }' "$$config" > "$$config.tmp" && mv "$$config.tmp" "$$config"; \
		fi; \
		if grep -q '^ddev_version_constraint:' "$$config"; then \
			perl -0pi -e "s/^ddev_version_constraint:.*\$$/ddev_version_constraint: '>= 1.24.0'/m" "$$config"; \
		else \
			printf "%s\n" "ddev_version_constraint: '>= 1.24.0'" >> "$$config"; \
		fi; \
	fi

dev-sync-source:
	@mkdir -p "$(TESTER_DIR)/source"
	rsync -a --delete \
		--delete-excluded \
		--exclude='.git/' \
		--exclude='.ddev/' \
		--exclude='.DS_Store' \
		--exclude='.drupal-contribute-fix/' \
		--exclude='.peer/' \
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
	$(MAKE) dev-normalize-ddev-config TESTER_DIR="$(TESTER_DIR)" TESTER_NAME="$(TESTER_NAME)"
	$(MAKE) dev-sync-source TESTER_DIR="$(TESTER_DIR)"
	cd "$(TESTER_DIR)" && ddev composer config repositories.umami path source
	cd "$(TESTER_DIR)" && ddev composer config repositories.umami_next path source/packages/umami_next
	cd "$(TESTER_DIR)" && ddev composer config repositories.umami_next_theme path source/packages/umami_next_theme
	cd "$(TESTER_DIR)" && ddev composer require drupal/umami:^1@dev drupal/umami_next:^1@dev drupal/umami_next_theme:^1@dev --with-all-dependencies
	cd "$(TESTER_DIR)" && ddev drush site:install "recipes/$(RECIPE)" --site-name=Umami --account-name=admin --account-pass=admin -y
	cd "$(TESTER_DIR)" && ddev drush cr
