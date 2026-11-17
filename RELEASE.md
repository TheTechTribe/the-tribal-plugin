# Releasing The Tribal Plugin to WordPress.org

One script does it all: `./release.sh`. Run it from the plugin folder, in bash on Linux/WSL.

```bash
./release.sh
```

1. It checks that the version numbers agree (see step 1 below).
2. It builds `dist/svn/`, the plugin files exactly as they ship.
3. It asks **"Create the zip?"** If yes, it writes `dist/zip/TTT_WordPress_Plugin.<VERSION>.zip` with the same files inside a `the-tech-tribe/` folder.
4. It asks **"Release to WordPress.org SVN?"** Answer `y` to release, `d` for a dry run, or `N` to stop.

`dist/` is gitignored and rebuilt on every run.

**What ships:** only an allowlist (`admin app assets helpers includes languages public index.php the-tribal-plugin.php uninstall.php README.txt LICENSE.txt`) plus a production `vendor/`. The script generates that `vendor/` with `composer install --no-dev` inside `dist/svn`. If you add a new top-level file or folder that must ship, add it to `SHIP=(...)` in `release.sh`.

`vendor/` is **not** in Git. For local development, run `composer install` in the plugin root.

**Credentials:** Keeper → search "WordPress". Use the username and the **SVN password**, not the WordPress.org login password. SVN asks for the password itself, so never pass it on the command line.

## How WordPress.org picks the release

- It reads `Stable tag:` from `trunk/README.txt` and serves the files in `tags/<Stable tag>/`.
- The version users see comes from `Version:` in `the-tribal-plugin.php`.
- So a release is: matching version numbers, trunk committed, and `tags/<VERSION>` created.
- **Tags are never overwritten.** To fix a mistake, release a new patch version.

---

## Release steps

### 1. Prepare the version (Git)

Set the same `<VERSION>` in all four places. The script stops if they don't match.

- `the-tribal-plugin.php`: `* Version:`
- `the-tribal-plugin.php`: `THE_TRIBAL_PLUGIN_VERSION`
- `README.txt`: `Stable tag:`
- `README.txt`: a changelog entry `= <VERSION> - <date> =`

Also update `Tested up to:` if needed. Run the tests (`tests/README.md`).

### 2. Build the zip and test it

Run `./release.sh`, answer **y** to the zip and **N** to SVN. Then check the file list:

```bash
unzip -Z1 dist/zip/TTT_WordPress_Plugin.<VERSION>.zip
```

On a test site running the current public version, upload the zip: Plugins → Add New → Upload, then "Replace current". Check activation, the dashboard tabs, the API key, and an import.

### 3. Commit and push to Git

The real SVN release refuses to run with uncommitted changes. It also warns if `HEAD` isn't pushed.

### 4. Dry run

Run `./release.sh` and answer **d** at the SVN question. It shows exactly what would change in trunk, then reverts. Nothing is sent to WordPress.org.

### 5. Release

Run `./release.sh` and answer **y** at the SVN question. The script:

1. Stops if `tags/<VERSION>` already exists or trunk has a newer version.
2. Checks out trunk to `~/svn/the-tech-tribe-trunk` on first use (it asks first), or updates it. It stops if that copy has local changes. To use another path, set `SVN_WC=<path>`.
3. Syncs `dist/svn/` into trunk and stages added and deleted files.
4. Shows `svn status` and offers the full diff.
5. Asks for your WordPress.org username (or set `WPORG_USER=<name>`), then asks you to **type the version** before it commits trunk. SVN prompts for the password.
6. Asks again, then creates `tags/<VERSION>` from **exactly the revision it just committed** (`trunk@<REV>`).
7. Checks that the tag matches that revision.

If it stops halfway, fix the cause and run it again:

- If the commit didn't happen, nothing was published.
- If trunk was committed, the script sees trunk already matches the build and goes straight to tagging.

If release confirmation is on for the plugin, approve the release from the email or the plugin's Advanced tab. Until then, users don't get it.

### 6. Verify (allow a few minutes; update notices can take longer)

```bash
# WordPress.org reports the new version
curl -s 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=the-tech-tribe' \
  | grep -o '"version":"[^"]*"'

# The public zip has the same files as our build (prints nothing)
curl -sLo /tmp/wporg.zip https://downloads.wordpress.org/plugin/the-tech-tribe.<VERSION>.zip
diff <(cd dist/svn && find . -type f | sed 's|^\./|the-tech-tribe/|' | sort) <(unzip -Z1 /tmp/wporg.zip | grep -v '/$' | sort)
```

- Check that https://wordpress.org/plugins/the-tech-tribe/ shows the new version and changelog.
- On a site running the previous version, check that Dashboard → Updates offers `<VERSION>`, then update it and check that the plugin works.

### 7. Announce

Follow the ClickUp SOP "Releasing a new version of the Wordpress Plugin" (community thread, Tribal Digest, Client Support). Link to the WordPress.org zip from step 6.

---

## When something goes wrong

| Situation | What to do |
|---|---|
| `429 Too Many Requests` from SVN | WordPress.org rate-limits parallel requests. The Linux/WSL svn client opens several connections by default, so the script forces `http-max-connections=1` on every call. If you run svn by hand, add `--config-option servers:global:http-max-connections=1`. If you still hit a 429, wait a minute, run `svn cleanup` in `~/svn/the-tech-tribe-trunk`, then rerun. |
| Script says the SVN working copy has leftover changes | Answer `y` to reset it. It's only a mirror of trunk, so nothing is lost. If that fails, delete `~/svn/the-tech-tribe-trunk` and rerun; the script checks it out again. |
| `tags/<VERSION>` already exists | Don't overwrite it. Bump to the next patch version and start again from step 1. |
| A bad release is live | Ship a fixed patch version. To point users back to an older version temporarily, set `Stable tag:` in trunk's `README.txt` to the previous tag and commit. Get team approval first. Don't delete tags. |
| Wrong files reached trunk | Fix the allowlist in `release.sh` and release a new patch version. |

## References

- Using Subversion: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/
- How readme.txt / Stable tag works: https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- Release confirmation: https://developer.wordpress.org/plugins/wordpress-org/release-confirmation-emails/
- SVN basic work cycle: https://svnbook.red-bean.com/en/1.7/svn.tour.cycle.html
