# Branch protection for `main`

After the CI workflow has run at least once on a pull request, configure **Settings → Branches → Add branch protection rule** for `main`:

| Setting | Value |
| --- | --- |
| Require a pull request before merging | On |
| Require status checks to pass before merging | On |
| Required status check | **Pint, PHPStan, PHPUnit** |
| Require branches to be up to date before merging | On (recommended) |
| Do not allow bypassing the above settings | On (if you have admin access) |

If the check name is missing from the dropdown, merge a PR that includes `.github/workflows/ci.yml`, wait for one green CI run, then return to this screen.

**Rulesets:** If the repository uses GitHub Rulesets instead of classic branch protection, add the same required check name to the ruleset that targets `main`.
