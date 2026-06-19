# LunarSystem build helpers

SASS ?= sass
SCSS_SRC := scss/luna.scss
CSS_OUT  := css/luna.css

.PHONY: css css-watch css-min

css: ## Compile scss/ -> css/luna.css (readable output)
	$(SASS) $(SCSS_SRC):$(CSS_OUT) --no-source-map --no-charset --quiet-deps --load-path=scss/vendor --style=expanded

css-watch: ## Recompile on every save while you work
	$(SASS) --watch $(SCSS_SRC):$(CSS_OUT) --no-source-map --no-charset --quiet-deps --load-path=scss/vendor --style=expanded

css-min: ## Compile minified (optional)
	$(SASS) $(SCSS_SRC):$(CSS_OUT) --no-source-map --no-charset --quiet-deps --load-path=scss/vendor --style=compressed
