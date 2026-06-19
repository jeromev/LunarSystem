# LunarSystem build helpers

SASS ?= sass
SCSS_SRC := scss/luna.scss
CSS_OUT  := css/luna.css
# Shared flags: UTF-8 without @charset, silence vendored-lib deprecations,
# resolve the vendored baseline grid via a load path.
SASS_FLAGS := --no-charset --quiet-deps --load-path=scss/vendor

.PHONY: css css-watch css-min

css: ## Compile scss/ -> css/luna.css (clean, no source map — run before committing)
	$(SASS) $(SCSS_SRC):$(CSS_OUT) --no-source-map $(SASS_FLAGS) --style=expanded

css-watch: ## Dev loop: recompile on save WITH source maps (dev tools show _*.scss:line)
	$(SASS) --watch $(SCSS_SRC):$(CSS_OUT) --embed-sources $(SASS_FLAGS) --style=expanded

css-min: ## Compile minified (optional)
	$(SASS) $(SCSS_SRC):$(CSS_OUT) --no-source-map $(SASS_FLAGS) --style=compressed
