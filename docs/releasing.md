# Releasing PayamBridge

The source repository contains two Composer packages: `payambridge/sdk` at the root and `payambridge/laravel` in `packages/laravel`. A stable `vMAJOR.MINOR.PATCH` tag releases both packages at the same version. The release workflow tests the tagged source, resolves both packages together at that version, pushes the Laravel subtree to its own GitHub repository with a matching tag, requests Packagist updates for both repositories, and creates release notes on the source repository. Do not tag a commit until its changes are on `main`.

## One-time setup

1. Make the source repository public on GitHub and enable GitHub Actions with permission to create releases (`contents: write`). Protect `main` and require the **CI** checks that matter to your project. The workflow uses the repository's `GITHUB_TOKEN` for release notes.
2. Create a **public, empty** GitHub repository for the Laravel package. Set the source repository variable `LARAVEL_SPLIT_REPOSITORY` to its `owner/repo` name. Do not initialize it with a README or unrelated commit. Give the source repository secret `LARAVEL_SPLIT_TOKEN` a fine-grained GitHub token with **Contents: Read and write** on that Laravel repository. This token must be allowed to push its `main` branch and tags.
3. After the release changes are merged to the source repository's `main`, bootstrap the split repository's `main` branch before submitting it to Packagist. From a current local checkout of source `main`, run the following commands, replacing `OWNER/LARAVEL_REPO` with the repository from step 2. Authenticate the push with a credential allowed to write to that repository. Do not create a tag here.

   ```sh
   git switch main
   git pull --ff-only
   split_sha="$(git subtree split --prefix=packages/laravel HEAD)"
   git push https://github.com/OWNER/LARAVEL_REPO.git "$split_sha:refs/heads/main"
   ```

   Set the Laravel repository's default branch to `main`. Keep its branch history limited to subtree splits; the release workflow will fast-forward this branch and create matching tags.
4. Register `payambridge/sdk` on Packagist from the source repository and `payambridge/laravel` from the now-populated split repository. Verify both package names and their repository URLs before tagging. Use a Packagist account able to update both packages. Set the source repository variable `PACKAGIST_USERNAME` and secret `PACKAGIST_API_TOKEN` from that account. The workflow calls Packagist's update-package API for each repository after the tags exist. A successful API response means the update was accepted; verify the versions appear on Packagist afterward. Packagist's GitHub webhook integration may also be enabled for subsequent pushes, but the action sends explicit update requests.
5. Set the source repository's release notes and package descriptions as desired. Check that the root and Laravel `composer.json` files, package licenses, and README files are current. The Laravel manifest must require a stable SDK version range that accepts the tag; the release preflight resolves both exact versions and fails before publishing if it cannot.

## Release checklist

1. Review `CHANGELOG.md`, open limitations in `docs/IMPLEMENTATION_STATUS.md`, and any security advisories. The current code is an initial slice; passing CI does not establish live provider acceptance or production readiness.
2. Run `composer install`, `composer check`, `composer validate --strict packages/laravel/composer.json`, `composer audit`, and `php tools/release-preflight.php v0.1.0` (substitute the planned tag). Run the clean consumer checks for `php`, `12`, and `13` if dependencies or packaging changed. These commands require Composer access to package repositories.
3. Merge the release changes into `main` and wait for CI. Create and push an annotated tag on that commit:

   ```sh
   git switch main
   git pull --ff-only
   git tag -a v0.1.0 -m 'PayamBridge v0.1.0'
   git push origin v0.1.0
   ```

4. Watch the **Release** action through all verification and publication steps. Confirm the split repository has the matching tag and its `main` branch is current. Confirm both Packagist pages show the version and that the GitHub release notes exist. Packagist indexing may lag behind the accepted update request.

A failed job can be rerun after its cause is fixed. The split push is atomic for its branch and tag; rerunning with the same split commit is safe. If validation fails before the split, nothing is published by this workflow. The source tag is already public when a tag push starts the workflow. If the split or Packagist step fails, the two packages may temporarily have different public availability; fix the failure and rerun the job before advertising the release. Never force-move a published tag. For a changed release commit, create a new version.
