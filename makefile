# SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
app_name=approval

project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build/artifacts
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates

all: appstore

release: appstore

clean:
	rm -rf $(build_dir)
	rm -rf node_modules

appstore: clean
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude=.git \
	--exclude=appinfo/signature.json \
	--exclude=*.swp \
	--exclude=build \
	--exclude=.gitignore \
	--exclude=.travis.yml \
	--exclude=.scrutinizer.yml \
	--exclude=CONTRIBUTING.md \
	--exclude=composer.json \
	--exclude=composer.lock \
	--exclude=composer.phar \
	--exclude=package.json \
	--exclude=package-lock.json \
	--exclude=js/node_modules \
	--exclude=node_modules \
	--exclude=src \
	--exclude=translationfiles \
	--exclude=webpack.* \
	--exclude=stylelint.config.js \
	--exclude=.eslintrc.js \
	--exclude=.github \
	--exclude=.gitlab-ci.yml \
	--exclude=crowdin.yml \
	--exclude=tools \
	--exclude=.tx \
	--exclude=.l10nignore \
	--exclude=l10n/.tx \
	--exclude=l10n/l10n.pl \
	--exclude=l10n/templates \
	--exclude=l10n/*.sh \
	--exclude=l10n/[a-z][a-z] \
	--exclude=l10n/[a-z][a-z]_[A-Z][A-Z] \
	--exclude=l10n/no-php \
	--exclude=makefile \
	--exclude=screenshots \
	--exclude=phpunit*xml \
	--exclude=tests \
	--exclude=ci \
	--exclude=vendor/bin \
	$(project_dir)/ $(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name).tar.gz | openssl base64; \
	fi
