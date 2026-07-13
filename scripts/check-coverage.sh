#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

mkdir -p build

vendor/bin/phpunit -c phpunit.docker.xml --coverage-clover=build/coverage.xml "$@"

php -r "
    \$xml = simplexml_load_file('build/coverage.xml');
    \$metrics = \$xml->project->metrics;
    \$covered = (int) \$metrics['coveredstatements'];
    \$total = (int) \$metrics['statements'];
    \$percent = \$total > 0 ? round((\$covered / \$total) * 100, 2) : 100;
    echo \"Line coverage: {\$percent}% ({\$covered}/{\$total})\n\";
    if (\$percent < 100) {
        exit(1);
    }
"
