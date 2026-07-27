# Updating Snipe-IT (this fork)

How to pull in a new upstream Snipe-IT release and publish updated Docker images.

Upstream is **`grokability/snipe-it`** (the old `snipe/snipe-it` redirects here).
This fork carries custom commits, so upstream is never merged straight into
production — it flows through a staging branch first.

```
upstream release
  └─ sync workflow ▶ merge + npm run prod ▶ PR into develop   (staging)
                       └─ validate the develop image
                            └─ PR develop ▶ master             (production gate)
                                 └─ tag vX.Y.Z ▶ latest image
```

- **`develop`** — integration/staging. Upstream lands here first.
- **`master`** — production. Only reviewed, validated code arrives via a `develop → master` PR.
- **`latest`** image — only ever from a `vX.Y.Z` release tag.

---

## One-time prerequisites

1. **Merge the CI PRs** that introduce the sync workflow and the multi-arch
   Docker builds. The sync workflow only becomes active once it is on `master`.
2. **Allow Actions to open PRs:** *Settings → Actions → General → Workflow
   permissions →* enable **"Allow GitHub Actions to create and approve pull requests."**
3. *(Only if you ever merge locally)* register the compiled-asset merge driver
   once per clone:
   ```bash
   git config merge.ours.driver true
   ```

---

## Normal update — no clone required

Everything below is done from the GitHub UI.

### 1. A new upstream stable release is detected
The **Sync upstream release** workflow runs daily and opens a PR
(`Sync upstream release vX.Y.Z`) **into `develop`** automatically. To run it
immediately: *Actions → Sync upstream release → Run workflow*.

On a clean merge the workflow also **rebuilds the compiled assets**
(`npm ci && npm run prod`) and commits them, so the PR already contains new PHP
plus matching CSS/JS.

### 2. Review and merge the sync PR into `develop`
Merging builds the **`develop` / `develop-alpine`** image — your staging artifact.

### 3. Validate the staging image
```bash
docker pull ghcr.io/citadeliscybersecurity/snipe-it:develop
docker buildx imagetools inspect ghcr.io/citadeliscybersecurity/snipe-it:develop   # confirm amd64 + arm64
```
Smoke-test until satisfied.

### 4. Promote `develop → master`
Open a PR from `develop` into `master` (title e.g. `Release: upstream vX.Y.Z`),
review, and merge. Merging builds the **`testing` / `testing-alpine`** image
(final pre-release check).

### 5. Cut the release → `latest`
*Releases → Draft a new release →* new tag `vX.Y.Z`, target `master`,
**Generate release notes**, **Publish**. The `v*` tag builds and publishes
**`latest`**, `X.Y.Z`, `X.Y` (+ `-alpine`).

---

## When a clone IS needed

Only if a sync hits **real (non-asset) code conflicts** — the sync PR is flagged
for manual resolution. Resolve locally:

```bash
git fetch upstream --tags
git checkout develop && git pull
git merge vX.Y.Z          # compiled assets auto-resolve via merge=ours
# resolve the remaining conflicts, then:
npm ci && npm run prod    # rebuild assets from the merged source
git commit -am "sync upstream vX.Y.Z + rebuild assets"
git push
```

---

## What triggers which image

| Action | Image tag(s) |
|---|---|
| Sync PR merged → `develop` | `develop` / `develop-alpine` (staging) |
| `develop → master` PR merged | `testing` / `testing-alpine` |
| `gh release create vX.Y.Z` (tag) | `latest`, `X.Y.Z`, `X.Y` (+ `-alpine`) |

Base image: `ghcr.io/citadeliscybersecurity/snipe-it`

---

## Golden rules

- **Upstream always lands on `develop` first** — never merge a raw upstream drop straight to `master`.
- **`latest` only ever comes from a `vX.Y.Z` tag** — routine commits never touch it.
- Compiled assets stay committed (the Docker image copies them in; there is no
  in-image build step). The sync workflow rebuilds them for you on a clean merge;
  if you merge manually, run `npm run prod` yourself.
