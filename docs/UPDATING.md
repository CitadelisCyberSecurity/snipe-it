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
                                 └─ latest + 8.6.3 images, automatically
```

- **`develop`** — integration/staging. Upstream lands here first.
- **`master`** — production. Only reviewed, validated code arrives via a `develop → master` PR.
- **`latest`** image — every push to `master`. Merging the production gate *is* the release; there is no separate tagging step.

**Image versions track upstream, not the fork.** The version tags come from
`app_version` in `config/version.php` — upstream's own version file, carried in
by the sync — so an image containing upstream 8.6.3 is published as `8.6.3`.
No git tag is involved, which is what previously caused an image containing
upstream 8.6.3 to be published as `1.1.0`.

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

### 4. Promote `develop → master` — this publishes the release
Open a PR from `develop` into `master` (title e.g. `Release: upstream vX.Y.Z`),
review, and merge. Merging publishes **`testing`** (+ `-alpine`) — the release
candidate. It does **not** move `latest`.

### 5. Promote to production

Actions → **Promote to production** → Run workflow. That retags the validated
`testing` digest as **`latest`**, **`X.Y.Z`** and **`X.Y`** (+ `-alpine`) —
both flavours in one run, no rebuild.

There is no tag to push and no release to draft. If you want a GitHub Release for
the changelog, create one against `master`; it has no effect on the images. See
[RELEASING.md](RELEASING.md) for why the gate is a dispatch and not a tag push.

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
| `develop → master` PR merged | `testing` (+ `-alpine`) — release candidate |
| **Promote to production** dispatch | `latest`, `X.Y.Z`, `X.Y` (+ `-alpine`) — production |

Base image: `ghcr.io/citadeliscybersecurity/snipe-it`

`X.Y.Z` is the **upstream** Snipe-IT version from `config/version.php` (today
`8.6.3`), so `latest`, `8.6.3` and `8.6` are three names for the same manifest.
`testing` is the last build of `master`. Right after a promote it points at that
same manifest — the promote is a retag, not a rebuild — and it diverges again on
the next merge to `master`. Those are the **only** tags published: no `vX.Y.Z`
git tag is involved and there is no per-commit `<branch>-<sha>` tag.

### Pinning and rollback

Every published tag **moves**, including `8.6.3` — fork commits land on top of an
upstream release, so successive `master` builds republish that same version. To
hold a deployment on one exact build, pin the digest:

```bash
# read the digest of what you have validated
docker buildx imagetools inspect ghcr.io/citadeliscybersecurity/snipe-it:latest

APP_VERSION=8.6.3 docker compose up -d   # tracks the newest 8.6.3 build
```

```yaml
# or pin exactly, in a compose override
image: ghcr.io/citadeliscybersecurity/snipe-it@sha256:<digest>
```

Digests are immutable and survive cleanup as long as a live tag points at them.
To roll back further, or to rebuild a specific commit, re-run the docker workflow
on that ref (Actions → *Docker images (Ubuntu)* / *Docker images (Alpine)* →
**Run workflow**) — note this republishes the moving tags from that commit.

`GHCR Cleanup` (`.github/workflows/ghcr-cleanup.yml`) prunes untagged and
orphaned versions weekly, so record the digest of anything you need to keep
reachable.

---

## Golden rules

- **Upstream always lands on `develop` first** — never merge a raw upstream drop straight to `master`.
- **Every push to `master` publishes `latest`.** `master` is the release branch, so
  validate on `develop` before promoting — there is no tagging step left to catch
  a bad merge.
- **Never hand-edit `app_version` in `config/version.php`** to change an image tag.
  It is upstream's file, owned by the sync; editing it makes the image claim an
  upstream release it does not contain.
- Compiled assets stay committed (the Docker image copies them in; there is no
  in-image build step). The sync workflow rebuilds them for you on a clean merge;
  if you merge manually, run `npm run prod` yourself.
