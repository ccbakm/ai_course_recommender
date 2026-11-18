# Contributing to AI Course Recommender

This document records how the AI Course Recommender was developed and maintained for the SEC101 Git, GitHub and Markdown assignment, including feature development, pull requests, code reviews, and merge-conflict resolution.

> **Individual work:** This assignment workflow was completed by one contributor, Ayush (`ccbakm`), using a single GitHub account. The two feature branches represent separate development workstreams, **not two different students**. Review comments were self-reviews, not approvals by an independent teammate. The original assignment specifies a two-student group; permission to submit individually is not confirmed here.

## 1. Individual Contributions

| Contributor | GitHub | Workstream | Branch | Feature commits | Pull request |
| --- | --- | --- | --- | --- | --- |
| Ayush | [@ccbakm](https://github.com/ccbakm) | Authentication: remove broken link, password visibility on login and registration, stronger registration password rules and live feedback | `feature-member1` | 4 | [PR #1](https://github.com/ccbakm/ai_course_recommender/pull/1) |
| Ayush | [@ccbakm](https://github.com/ccbakm) | Learning plans: live title/domain search, clear-search and no-results feedback, activation/deactivation fix, active/inactive filters | `feature-member2` | 4 | [PR #2](https://github.com/ccbakm/ai_course_recommender/pull/2) |

Both branches were merged into `main` through GitHub pull requests. The repository also includes initial setup commits and the later merge-conflict demonstration.

## 2. Branching Strategy

| Branch | Purpose |
| --- | --- |
| `main` | Main application branch, containing the completed feature branches and conflict-resolution work |
| `feature-member1` | Authentication and registration improvements |
| `feature-member2` | Learning plan search, filtering and activation improvements |
| `conflict-member1` | Deliberate README edit describing plan search |
| `conflict-member2` | Different edit to the same README line describing status filtering |

For feature development, each branch started from `main`, received small, meaningful commits, was pushed to GitHub and was merged through a pull request. The conflict branches were used to demonstrate a real merge conflict, not to represent extra contributors.

## 3. Commit Guidelines and Actual Feature Commits

Commits should make one logical, testable change at a time, with short descriptive messages such as `Add`, `Fix`, `Improve` or `Remove`. Do not commit confidential files, passwords or API keys. In particular, the local `config.php` is excluded using `.gitignore`.

### `feature-member1`

1. `a7baa8c` — Remove broken link to non-existent forgot-password page
2. `e1573f1` — Add show and hide password toggle to login page
3. `72c3263` — Add password visibility toggles to registration form
4. `12f1056` — Improve registration password validation with live feedback

### `feature-member2`

1. `d7b2866` — Add live search for saved learning plans
2. `c99e6fc` — Improve plan search with clear button and no-results feedback
3. `61f5e85` — Fix learning plan activation and deactivation toggle
4. `881305c` — Add learning plan status filtering

These hashes and messages were read from the uploaded repository's local Git history. Additional setup, merge and documentation commits can be viewed using:

```bash
git log --oneline --graph --all
```

## 4. Pull Request Process

1. Complete and locally test the changes on the appropriate feature branch.
2. Push the branch to GitHub.
3. Create a pull request targeting `main`, with a summary and testing notes.
4. Inspect the **Files changed** tab, add a review comment, and address issues if needed.
5. Merge the pull request into `main` using GitHub's **Create a merge commit** option.
6. Switch to local `main` and pull the merged changes.

### Completed Pull Requests

| PR | Source → Target | Changes | Result |
| --- | --- | --- | --- |
| [#1 — Improve user authentication and registration experience](https://github.com/ccbakm/ai_course_recommender/pull/1) | `feature-member1` → `main` | Password visibility and improved registration validation | Merged |
| [#2 — Improve learning plan management with search and status filters](https://github.com/ccbakm/ai_course_recommender/pull/2) | `feature-member2` → `main` | Search, clear button, no-results feedback, status filters and activation controls | Merged |

## 5. Code Review Record

Both pull requests were inspected by the same contributor who implemented them. Inline comments were published to document a **self-review**. GitHub does not allow authors to formally approve their own pull requests, so these comments should not be represented as independent approvals.

| Pull request | Reviewer | Review observation | Evidence |
| --- | --- | --- | --- |
| [PR #1](https://github.com/ccbakm/ai_course_recommender/pull/1) | Ayush (self-review) | Confirmed server-side password validation operates independently of JavaScript; suggested automated validation tests in future | `pr1-code-review.png` |
| [PR #2](https://github.com/ccbakm/ai_course_recommender/pull/2) | Ayush (self-review) | Confirmed search and status filters work together and full-width card layout is preserved; suggested showing a match count in future | `pr2-code-review.png` |

Screenshots of the merged pull requests were also saved as `pr1-merged.png` and `pr2-merged.png`.

## 6. Merge Conflict Demonstration and Resolution

A deliberate conflict was generated in the **Plan management** feature description in `README.md`:

1. `conflict-member1` changed the line to mention searching saved plans by title or domain.
2. `conflict-member2`, created separately from the original `main`, changed that exact line to mention filtering by active/inactive status.
3. The first branch was merged into `main` successfully.
4. Merging the second branch into `main` produced a real **content merge conflict** in `README.md`.
5. The conflict was inspected and photographed in VS Code, and the final README description combined both changes into one line.
6. A merge-resolution commit was created (`7115087`, `Resolve README merge conflict by combining plan management descriptions`). A subsequent correction commit removed conflict markers that had remained in the committed README. The correction was pushed to GitHub.

The final intended description is:

> **Plan management** – save plans, view all plans, set one plan as *active*, delete plans, search saved plans by title or domain, and filter plans by active or inactive status.

Conflict evidence saved locally:

- `conflict-1-terminal.png` — merge conflict in the terminal
- `conflict-2-status.png` — Git reporting unmerged paths
- `conflict-3-markers.png` — both conflicting versions in the editor
- `conflict-4-resolved.png` — combined description in the editor
- `conflict-5-ready-to-commit.png` — Git status after staging the resolution
- `conflict-6-merge-graph.png` — branch graph showing the merge

These files are intended as evidence for the PDF report. They need not be committed to the public source-code repository unless explicitly required.

## 7. Local Setup and Security

See [Installation and Execution](README.md#-installation-and-execution) in the README for the application setup steps.

Keep secrets in local `config.php`, not in tracked source files or documentation. Never publish API keys, database passwords, session tokens or other confidential values. If a key is exposed, revoke it and create a replacement.
