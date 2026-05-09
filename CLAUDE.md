# laravel-bigquery-eloquent

A Laravel database driver that lets Eloquent models query Google BigQuery tables. Composer name: `nomansheikh/laravel-bigquery-eloquent`.

This is a **non-PDO driver** — BigQuery has no PDO support, so the package overrides Laravel's `Connection`, `Grammar`, and `Builder` classes to talk to BigQuery via the `google/cloud-bigquery` PHP SDK directly. The same architectural pattern as `mongodb/laravel-mongodb` and `kitar/laravel-dynamodb`.

## Stack

- PHP 8.3+
- Laravel 11.x / 12.x
- `google/cloud-bigquery ^1.34`
- `spatie/laravel-package-tools ^1.16` for the service provider boilerplate
- Pest 2/3 + Orchestra Testbench for tests
- Larastan for static analysis, Pint for formatting

## Architecture

```
src/
├── LaravelBigqueryEloquentServiceProvider.php  registers `bigquery` driver via $db->extend()
├── BigQueryConnection.php                      extends Illuminate\Database\Connection
├── Eloquent/
│   ├── BigQueryModel.php                       abstract base; auto-prefixes table with project.dataset
│   └── BigQueryEloquentBuilder.php             Eloquent builder override
├── Query/
│   ├── BigQueryGrammar.php                     SQL generation (backtick-wraps table refs)
│   ├── BigQueryQueryBuilder.php                executes queries via the BigQuery client
│   └── BigQueryProcessor.php
└── Schema/
    ├── BigQuerySchemaBuilder.php
    └── BigQuerySchemaGrammar.php
```

User config lives in `config/database.php` under `connections.bigquery`. Keys: `driver`, `project_id`, `dataset`, optional `key_file` (path string OR credential array).

## Reference packages

When unsure how something should look, consult the two canonical non-PDO Eloquent drivers — both are mature and follow Laravel conventions tightly:

- [`mongodb/laravel-mongodb`](https://github.com/mongodb/laravel-mongodb) — best architectural reference. `src/Connection.php` shows the right way to bypass PDO; `src/Eloquent/DocumentModel.php` shows non-relational `getAttribute`/`qualifyColumn` adaptations.
- [`kitar/laravel-dynamodb`](https://github.com/kitar/laravel-dynamodb) — smaller, cleaner example of the same pattern.

## Coding standards

The Spatie PHP guidelines are the project's coding standard — invoke the `php-guidelines-from-spatie` skill when working on PHP/Laravel code. Highlights:

- No docblocks for fully type-hinted methods.
- Early returns; avoid `else`. Split compound `&&` conditions into nested `if`s.
- Constructor property promotion when all properties can be promoted.
- `?Type` not `Type|null`.
- Always import classes via `use` — never inline FQCN like `\Exception`.
- String interpolation over concatenation.
- No comments that describe *what* the code does; only *why* when non-obvious.

Pint enforces formatting; Larastan enforces types. Both should pass.

## Notes on BigQuery specifics

- **Bindings**: BigQuery's PHP SDK `parameters($array)` switches between positional (`?` placeholders, sequential array) and named (`@name`, assoc array) based on whether the array is associative. Laravel always passes positional. Untyped `null` and `DateTimeInterface` bindings need explicit type handling — see [BigQuery parameterized queries docs](https://docs.cloud.google.com/bigquery/docs/parameterized-queries).
- **Table naming**: `BigQueryModel::getTable()` auto-prefixes `$table` with `project.dataset` if the model's table name doesn't already contain a dot.
- **Grammar wrap**: `BigQueryGrammar::wrap()` short-circuits on `*`, anything containing `.`, and anything containing backticks. This bypasses Laravel's parent `wrap()` entirely — don't rely on parent semantics here.
- **No PDO**: anything in Laravel core or third-party packages that calls `$connection->getPdo()` will not work; the connection class needs explicit overrides for `select`, `transaction`, etc.

## Common commands

```bash
composer test          # run Pest suite
composer test-coverage # with coverage
composer analyse       # phpstan
composer format        # pint
```

## Workflow rules

- **Always run `composer test` before `git add`.** Never stage changes that haven't been verified locally. If tests fail, fix the cause before staging.

## Repo conventions

- **Commits**: conventional commits style (`feat:`, `fix:`, `refactor:`). Use the `/commit` skill.
- **Issue templates**: live in `.github/ISSUE_TEMPLATE/`. The bug template requires version fields; for architecture/refactor issues, create via `gh issue create --title --body --label` to bypass the template.
- **PR descriptions**: no "Test plan" section, no Claude Code mentions.
- **Feature requests**: routed to GitHub Discussions per the issue config.
