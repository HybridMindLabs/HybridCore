# Contributing to HybridCore

Thank you for your interest in contributing to HybridCore.

Please read this document fully before opening a pull request.

---

## Branch Workflow

HybridCore uses a structured branch model:

| Branch | Purpose |
|---|---|
| `main` | Stable, always deployable |
| `develop` | Integration branch for completed features |
| `feature/*` | New features in progress |
| `fix/*` | Bug fixes |
| `chore/*` | Tooling, dependency updates, non-functional changes |
| `docs/*` | Documentation only |
| `release/*` | Release preparation |
| `hotfix/*` | Emergency patches to `main` |

**Rules:**

- Never commit directly to `main`.
- All work starts from `develop` and merges back to `develop` via pull request.
- Release branches branch from `develop` and merge to both `main` and `develop`.
- Hotfixes branch from `main` and merge to both `main` and `develop`.

---

## Commit Message Style

HybridCore follows [Conventional Commits](https://www.conventionalcommits.org/).

```
<type>(<scope>): <short description>

[optional body]

[optional footer]
```

**Types:**

| Type | When to use |
|---|---|
| `feat` | New feature |
| `fix` | Bug fix |
| `chore` | Tooling, build, dependency updates |
| `docs` | Documentation only |
| `refactor` | Code restructure without behaviour change |
| `test` | Adding or updating tests |
| `style` | Formatting, whitespace, no logic change |
| `perf` | Performance improvement |
| `ci` | CI/CD configuration changes |

**Examples:**

```
feat(admin): add user role assignment UI
fix(installer): correct database connection validation logic
chore(deps): update tailwindcss to 4.1
docs(contributing): clarify branch workflow
```

**Rules:**

- Limit the subject line to 72 characters.
- Use the imperative mood ("add" not "added", "fix" not "fixed").
- Reference issues in the footer: `Closes #42`

---

## Pull Request Expectations

- Every PR must target `develop`, never `main` directly.
- Every PR must have a clear title following the commit message format.
- Every PR must include a description explaining what changed and why.
- Every PR must pass CI before review.
- If a PR introduces UI changes, include screenshots.
- If a PR changes the extension or theme API, update the relevant docs.
- Small, focused PRs are preferred over large sweeping changes.

---

## Code Quality Expectations

**PHP:**

- PHP 8.3+ syntax and features.
- Follow Laravel conventions and idioms.
- Run `./vendor/bin/pint` before committing (Laravel Pint, PSR-12 with Laravel preset).
- No `dd()`, `var_dump()` or debug statements left in code.
- No hardcoded credentials or secrets.
- Eloquent queries must be efficient — avoid N+1, use eager loading.

**JavaScript / TypeScript:**

- Vue 3 Composition API with `<script setup>`.
- TypeScript types for all component props and emits.
- No inline styles — use TailwindCSS utility classes.
- Components must be self-contained and reusable where possible.

**General:**

- No commented-out dead code committed.
- No `TODO` comments committed unless they reference a tracked issue.
- All new features must have at least one automated test (feature or unit).

---

## Documentation Update Requirements

- Any change to the extension API must update [extensions/BUILDING_EXTENSIONS.md](extensions/BUILDING_EXTENSIONS.md).
- Any change affecting deployment or upgrades must update [DEPLOYMENT.md](DEPLOYMENT.md).
- The `CHANGELOG.md` must be updated in every PR that changes user-facing behaviour.

---

## Local Development Setup

See [README.md](README.md#local-development) for setup instructions.

---

## Questions

Open a discussion in the repository or contact the core team directly.
