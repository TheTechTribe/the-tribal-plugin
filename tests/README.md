# Tests

WordPress integration tests (PHPUnit 9.6 + the WordPress test library).

Test tooling lives in `tests/vendor` with its own `tests/composer.json`. The plugin's own
`vendor/` is not in Git: run `composer install` in the plugin root for local development.
`release.sh` generates a production `vendor/` inside `dist/` and never ships `tests/`.

## Setup (once per machine)

```bash
cd tests
composer install
# Only if $TMPDIR/wordpress-tests-lib does not exist yet (shared by the team's plugins):
bin/install-wp-tests.sh <test_db_name> <db_user> <db_pass> [db_host] [wp_version]
```

The test library uses its **own database**, which it empties on every run. Never point it at a
real site's database.

## Run

```bash
cd tests
vendor/bin/phpunit                         # everything
vendor/bin/phpunit --testdox               # readable list
vendor/bin/phpunit --exclude-group known-bug
vendor/bin/phpunit --group known-bug       # documented bugs that are not fixed yet
```

## How the tests work

- `TestCase.php` gives every test an isolated uploads/log folder, a configured plugin (API key,
  active, default author, manual publishing) and a **fake portal** via `pre_http_request`.
  No real HTTP request is ever made, and any unexpected one is counted and fails the request.
- The fake portal answers in the real shape of whichever API version the plugin calls (`v1`
  shapes from the portal's V1 classes, `v2` from its v2 API). That lets the same
  scenario-level assertions describe the required behaviour regardless of version.
- Scenarios: `posts`, `empty`, `invalid`, `ac_tag`, `domain`, `rate_limited`, `unavailable`,
  `upstream_down`, `timeout`, `not_json`. Set `$this->scenario` and `$this->portalPosts`.
- `@group known-bug` marks behaviour that is wrong today and expected to fail until fixed
  (currently none: write the failing test first, tag it, then fix and remove the tag).
