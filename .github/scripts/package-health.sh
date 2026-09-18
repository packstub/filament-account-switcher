#!/usr/bin/env bash
#
# Package health: the checks behind the score on filamentphp.com (Powered by Plumb, https://plumbphp.dev/checks),
# run on the commit instead of the last release, so a tag never ships a lower score.
#
#   bash .github/scripts/package-health.sh              the repository checks (every push)
#   bash .github/scripts/package-health.sh --published  the published score too (needs curl and jq)
#
# The same file lives in every open-source Packstub plugin; change it in all of them.

set -uo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/../.."

failures=0

pass() { printf '  \033[32m✓\033[0m %s\n' "$1"; }
fail() {
    printf '  \033[31m✗\033[0m %s\n' "$1"
    [ -n "${GITHUB_ACTIONS:-}" ] && echo "::error title=Package health::$1"
    failures=$((failures + 1))
}

tracked() { [ -n "$(git ls-files -- "$@")" ]; }

# What `composer require` downloads: `git archive` of the tag, shaped by .gitattributes.
archive=$(git archive --format=tar --worktree-attributes HEAD | tar -t | grep -v '/$')

echo 'Dist archive is lean'

# Plumb's four groups of development files (https://plumbphp.dev/checks/maintenance/lean-dist), read widely:
# anything an install never loads.
group() {
    local hits
    hits=$(echo "$archive" | grep -E "$2" || true)

    if [ -z "$hits" ]; then
        pass "no $1 files"
    else
        fail "$1 files ship in the archive, add them to .gitattributes as export-ignore: $(echo "$hits" | tr '\n' ' ')"
    fi
}

group 'test' '^([Tt]ests?|workbench)/|^(phpunit[^/]*\.xml(\.dist)?|\.phpunit[^/]*|phpstan[^/]*\.neon(\.dist)?|psalm[^/]*\.xml(\.dist)?|infection\.json5?(\.dist)?|testbench\.yaml|(playwright|vitest|jest)\.config\.[a-z]+|codecov\.ya?ml)$'
group 'CI' '^(\.github|\.gitlab|\.circleci)/|^(\.gitlab-ci\.yml|\.travis\.yml|\.scrutinizer\.yml|\.styleci\.yml|bitbucket-pipelines\.yml)$'
group 'AI assistant' '^(\.claude|\.cursor|\.agents|\.junie|\.windsurf|\.codex)/|^(CLAUDE\.md|AGENTS\.md|GEMINI\.md|\.cursorrules|\.windsurfrules|\.aider[^/]*|\.mcp\.json)$'
group 'tooling' '^(\.idea|\.vscode|\.husky|docker|art|docs)/|^(\.php-cs-fixer[^/]*|\.php_cs[^/]*|\.editorconfig|\.gitattributes|\.gitignore|\.prettierrc[^/]*|\.eslintrc[^/]*|eslint\.config\.[a-z]+|Makefile|rector\.php|ecs\.php|pint\.json|phpcs\.xml(\.dist)?|captainhook\.json|grumphp\.yml(\.dist)?|docker-compose[^/]*\.ya?ml|Dockerfile|CHANGELOG\.md|CONTRIBUTING\.md|UPGRADE\.md|UPGRADING\.md|package\.json|package-lock\.json|bun\.lockb?|yarn\.lock|pnpm-lock\.yaml|tsconfig[^/]*\.json|(vite|svelte|tailwind|postcss)\.config\.[a-z]+)$'

echo 'composer.lock not committed by library'

if echo "$archive" | grep -qx 'composer.lock'; then
    fail 'composer.lock ships in the archive'
else
    pass 'composer.lock is absent from the archive'
fi

echo 'GitHub Actions pinned to SHA'

unpinned=$(grep -rhoE '^\s*(-\s+)?uses:\s*[^ #]+' .github/workflows 2>/dev/null | sed -E 's/.*uses:[[:space:]]*//' | tr -d "\"'" | grep -vE '^\./' | grep -vE '@[0-9a-f]{40}$' | sort -u || true)

if [ -z "$unpinned" ]; then
    pass 'every third-party action is pinned to a commit SHA'
else
    fail "pin to a 40-character commit SHA: $(echo "$unpinned" | tr '\n' ' ')"
fi

echo 'Dependabot configured, with a cooldown, for every ecosystem'

config=.github/dependabot.yml

if [ ! -f "$config" ]; then
    fail "$config is missing"
else
    covers() { grep -qE "package-ecosystem:[[:space:]]*[\"']?$1[\"']?[[:space:]]*$" "$config"; }

    needs() {
        if covers "$1"; then
            pass "$1 is covered"
        else
            fail "$config has no \"$1\" entry, and $2 is committed"
        fi
    }

    tracked .github/workflows && needs 'github-actions' '.github/workflows'
    tracked composer.lock && needs 'composer' 'composer.lock'
    tracked bun.lock bun.lockb && needs 'bun' 'bun.lock'
    tracked package-lock.json yarn.lock pnpm-lock.yaml && needs 'npm' 'a JavaScript lockfile'

    entries=$(grep -cE '^\s*-\s*package-ecosystem:' "$config")
    cooldowns=$(grep -cE '^\s*cooldown:' "$config")

    if [ "$entries" -gt 0 ] && [ "$entries" -eq "$cooldowns" ]; then
        pass "a cooldown on each of the $entries entries"
    else
        fail "$cooldowns of the $entries entries in $config set a cooldown"
    fi
fi

echo 'Provides a security policy'

if tracked SECURITY.md .github/SECURITY.md docs/SECURITY.md; then
    pass 'SECURITY.md is present'
else
    fail 'add .github/SECURITY.md'
fi

if [ "${1:-}" = '--published' ]; then
    package=$(jq -r .name composer.json)

    echo "Published score of $package (Powered by Plumb, https://plumbphp.dev/$package)"

    if ! scan=$(curl -fsS --retry 3 "https://plumbphp.dev/api/v1/packages/$package" | jq -e '.data.latest_scan'); then
        fail 'the score could not be read from plumbphp.dev'
    else
        echo "$scan" | jq -r '"  \(.reference_version): \(.scores.composite) — security \(.scores.security), maintenance \(.scores.maintenance), ecosystem \(.scores.ecosystem)"'

        while IFS= read -r line; do
            [ -n "$line" ] && fail "$line"
        done < <(echo "$scan" | jq -r '.check_results[] | select(.status != "pass" and .status != "not_applicable") | "\(.title): \(.status) — \(.guide_url) \(.evidence | tojson)"')

        if echo "$scan" | jq -e '.scores.composite >= 100' > /dev/null; then
            pass 'the score is 100'
        else
            fail 'the score is below 100'
        fi
    fi
fi

echo

if [ "$failures" -gt 0 ]; then
    echo "$failures package health check(s) failed."
    exit 1
fi

echo 'Package health: all checks passed.'
