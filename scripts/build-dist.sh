#!/bin/sh
set -eu

plugin_slug="${PLUGIN_SLUG:-occidg}"
zip_name="${npm_package_name:-oneclickcontent-images}.zip"
root_dir="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
stage_root="${TMPDIR:-/tmp}/occidg-dist-$$"
stage_dir="$stage_root/$plugin_slug"

cleanup() {
	rm -rf "$stage_root"
}

trap cleanup EXIT HUP INT TERM

rm -f "$root_dir/$zip_name"
mkdir -p "$stage_dir"

cd "$root_dir"
for path in * .[!.]* ..?*; do
	if [ ! -e "$path" ]; then
		continue
	fi

	case "$path" in
		.git|"$zip_name")
			continue
			;;
	esac

	cp -R "$path" "$stage_dir"/
done

rm -rf \
	"$stage_dir/.codex" \
	"$stage_dir/.codex-backups" \
	"$stage_dir/.cstn-generator" \
	"$stage_dir/.git" \
	"$stage_dir/.github" \
	"$stage_dir/.wp-core" \
	"$stage_dir/.wp-tests" \
	"$stage_dir/assets" \
	"$stage_dir/build" \
	"$stage_dir/codex-yolo" \
	"$stage_dir/coverage" \
	"$stage_dir/dist" \
	"$stage_dir/node_modules" \
	"$stage_dir/scripts" \
	"$stage_dir/tests" \
	"$stage_dir/vendor"

rm -f \
	"$stage_dir/.codex_index.json" \
	"$stage_dir/.codex_run_meta.json" \
	"$stage_dir/.codex_tasks.csv" \
	"$stage_dir/.codex_test_output.log" \
	"$stage_dir/.gitignore" \
	"$stage_dir/.phpunit.result.cache" \
	"$stage_dir/AGENTS.md" \
	"$stage_dir/APP_FLOW.md" \
	"$stage_dir/CLEAN_DOCKER_REPRO.md" \
	"$stage_dir/DESIGN_LESSONS.md" \
	"$stage_dir/DESIGN_SYSTEM.md" \
	"$stage_dir/FRONTEND_GUIDELINES.md" \
	"$stage_dir/MEMORY.md" \
	"$stage_dir/PLAN.md" \
	"$stage_dir/PLAYBOOK.md" \
	"$stage_dir/README.md" \
	"$stage_dir/SPEC.md" \
	"$stage_dir/UI_AUDIT.md" \
	"$stage_dir/check.txt" \
	"$stage_dir/composer.json" \
	"$stage_dir/composer.lock" \
	"$stage_dir/package-lock.json" \
	"$stage_dir/package.json" \
	"$stage_dir/phpcs.xml.dist" \
	"$stage_dir/phpmd.txt" \
	"$stage_dir/phpmd.xml" \
	"$stage_dir/phpunit.xml.dist" \
	"$stage_dir/plugin-error.log"

rm -f "$stage_dir"/*.log "$stage_dir"/*.zip

cd "$stage_root"
zip -r "$root_dir/$zip_name" "$plugin_slug"
