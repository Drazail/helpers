#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

mkdir -p build

# Minimum line-coverage percentage required to pass. We deliberately do not
# require 100%: a few platform/error branches (e.g. the Windows-only argument
# escaping and broken-pipe/stream error handlers in Process) cannot be
# exercised on the Linux CI without gaming the report with ignore annotations.
MIN_COVERAGE="${MIN_COVERAGE:-95}"

vendor/bin/phpunit -c phpunit.docker.xml --coverage-clover=build/coverage.xml "$@"

MIN_COVERAGE="$MIN_COVERAGE" php -r "
    \$xml = simplexml_load_file('build/coverage.xml');
    \$metrics = \$xml->project->metrics;
    \$covered = (int) \$metrics['coveredstatements'];
    \$total = (int) \$metrics['statements'];
    \$percent = \$total > 0 ? round((\$covered / \$total) * 100, 2) : 100;
    \$min = (float) getenv('MIN_COVERAGE');
    echo \"Line coverage: {\$percent}% ({\$covered}/{\$total}), minimum required: {\$min}%\n\";
    if (\$percent < \$min) {
        exit(1);
    }
"
