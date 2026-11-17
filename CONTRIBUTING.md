# Contributing to AI Course Recommender

This document explains how work is organised in this repository: who did what, how branches are named, how pull requests are raised, and how code is reviewed.

> **Note:** Items marked `[TODO]` must be filled in with your real details as you complete the assignment tasks. Do not leave any `[TODO]` in the final submission.

---

## 1. Individual Contributions

| Contributor | GitHub | Features / areas | Branch | Commits | Pull request |
| ----------- | ------ | ---------------- | ------ | ------- | ------------ |
| [TODO: Name] | [@username](https://github.com/username) | [TODO: e.g. quiz generation, dashboard] | `[TODO: branch name]` | [TODO: number] | [TODO: #1 link] |

Contributions can be verified through the repository's **Commits**, **Pull requests** and **Insights → Contributors** pages on GitHub.

## 2. Branching Strategy

| Branch | Purpose |
| ------ | ------- |
| `main` | Stable, working code. Changes arrive here only through a reviewed pull request. |
| `feature-<name>` | One branch per set of related features, created from `main`. |

Create and publish a branch:

```bash
git checkout main
git pull
git checkout -b feature-your-feature
git push -u origin feature-your-feature
```

## 3. Commit Guidelines

- Make **small, focused commits** – one logical change each.
- Write the message in the imperative mood: `Add`, `Fix`, `Update`, `Remove`.
- Good examples:
  - `Add weak-point analysis endpoint`
  - `Fix broken forgot-password link on login page`
  - `Document installation steps in README`
- Never commit secrets (API keys, passwords). `config.php` is git-ignored on purpose.

```bash
git add <files>
git commit -m "Add weak-point analysis endpoint"
git push
```

## 4. Pull Request Process

1. Push your feature branch to GitHub.
2. Open a **pull request** from the feature branch into `main`.
3. Give it a clear title and a short description: *what* changed and *why*.
4. Request a review (see section 5).
5. Resolve all review comments.
6. Merge into `main` once approved, then update your local copy:

```bash
git checkout main
git pull
```

### Pull request template

```markdown
## What does this PR do?
<short summary>

## Changes
- <change 1>
- <change 2>

## How to test
1. <step>
2. <step>

## Checklist
- [ ] Code runs locally without errors
- [ ] No API keys or passwords committed
- [ ] README updated if needed
```

## 5. Code Review Guidelines

A reviewer checks that the change:

- [ ] does what the pull request says
- [ ] runs without errors or warnings
- [ ] follows the existing code style and file structure
- [ ] contains **no secrets** (keys, passwords, personal data)
- [ ] uses prepared statements for database queries and escapes output
- [ ] has clear commit messages

Review comments should be **specific and polite**, for example: *"Line 42 – please validate this input before the query."*

### Review log

| Pull request | Reviewer | Date | Outcome / main comments |
| ------------ | -------- | ---- | ----------------------- |
| [TODO: #1] | [TODO] | [TODO] | [TODO] |

## 6. Merge Conflicts

If two branches change the same line, Git stops the merge and marks the file:

```text
<<<<<<< HEAD
version from the current branch
=======
version from the branch being merged
>>>>>>> feature-your-feature
```

To resolve:

1. Open the file and decide what the final text should be.
2. Delete the `<<<<<<<`, `=======` and `>>>>>>>` marker lines.
3. Stage and commit the corrected file:

```bash
git add <file>
git commit -m "Resolve merge conflict in <file>"
```

**Conflict handled in this project:** [TODO: file name, the two conflicting changes, and how you resolved it]

## 7. Setting Up Locally

See [Installation and Execution](README.md#-installation-and-execution) in the README.
