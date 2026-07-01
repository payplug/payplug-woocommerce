## Description
<!-- Describe the changes introduced by this PR -->

## Related Issue
<!-- Link to the Jira ticket or GitHub issue -->
Ticket: [PRE-XXXX](https://payplug.atlassian.net/browse/PRE-XXXX)

## Type of Change
<!-- At least one required — replace [ ] with [x] -->
[ ] 🐛 Bug fix
[ ] ✨ New feature
[ ] 💥 Breaking change
[ ] ♻️ Refactor
[ ] 🔧 Configuration / CI
[ ] 🚀 Release (`release/*` branch targeting `master`)
[ ] 📦 Dependency update
[ ] 🔒 Security fix
[ ] 📝 Documentation update

---

## ✅ Quality Checklist

### Local Environment & Hooks
- [ ] Local Git hooks (**CaptainHook**) are installed and executed cleanly.
- [ ] Commit messages strictly follow the `(PRE|SMP)-XXXX: description` pattern.
- [ ] Core configuration files (`phpstan.neon` / `.php-cs-fixer.php`) were generated successfully from `.dist` templates.

### Testing & Code Quality
- [ ] Coding style rules have been applied locally (`composer cs:fix`).
- [ ] Static analysis checks pass with no new regressions (`vendor/bin/phpstan`).
- [ ] I have added/updated unit or integration tests if applicable.
- [ ] I have verified these changes locally on a native WooCommerce environment.

### CI/CD Deployment Context
- [ ] The CI pipeline passes fully on GitHub.
- [ ] **For Release Branches:** If this is a `release/*` branch, I am targeting the correct base branch to allow the automated `apply-release` version bumping job to run.

---

## Screenshots (if applicable)
<!-- Add screenshots or screen recordings if relevant -->

## Notes for Reviewer
<!-- Anything specific the reviewer should pay attention to -->
