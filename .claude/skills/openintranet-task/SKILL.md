---
name: openintranet-task
description: Use to execute an Open Intranet (Drupal) task end-to-end with the project's standard flow — intake, research, brainstorm, spec, plan, subagent execution (Opus 4.8), then quality gates (phpcs, phpstan, drupal-code-review, code-review, simplify, php-refactor-scan) and compact QA notes. Trigger when given a Drupal.org work-item link/number or a task description for this project.
---

# Open Intranet task flow

Standard, repeatable flow for an Open Intranet task. Drive it phase by phase; honor the human gates.

## Conventions (this project)
- Task notes: `tasks/<id>.md` (gitignored; `/tasks/` in `.gitignore`). Reformat the task here in **English**:
  Drupal.org link at top, branch name, base branch, the original description, then a "Current state analysis".
- Branch: `issue/<id>-<slug>` from the base branch (usually `1.9.x`).
- Theme source of truth is `starter-theme/openintranet_theme/`; `web/themes/custom/openintranet_theme/` is a
  gitignored build copy. Edit/verify in the runtime copy, then COPY changed files back to `starter-theme/`.
- Theme build: `ddev theme compile` (or `gulp styles` in the theme dir). CSS aggregation is on → run `ddev drush cr`
  after recompiling for changes to show.
- All docs/comments/task files in **English** (chat may be Polish).

## Phases
1. **Intake** — write `tasks/<id>.md`; create the branch; ensure `/tasks/` is gitignored.
2. **Research** — explore the code (inline / Explore agents / a Workflow when broad). Record findings in
   "Current state analysis". Prefer config over custom code; reuse existing patterns.
3. **Brainstorm** — invoke `superpowers:brainstorming`. Lock decisions; ask when unclear.
4. **Spec** — write decisions + plan into `tasks/<id>.md` (large efforts may also use `docs/superpowers/specs/`).
5. **Plan** — invoke `superpowers:writing-plans`.
6. **Execute** — implement via subagents on **Opus 4.8** (`Agent` with `model: opus`). Compile, verify in the
   browser (chrome-devtools MCP) as the right user, and sync changed theme files to `starter-theme/`.
7. **Quality gates** → **Fixes** (after approval) → commit on the task branch.

## Cadence
- **Small task:** run the quality gates once, at the end.
- **Large/multi-stage task:** after each completed execute chunk, run the relevant gates on that chunk
  (typically `simplify` + `code-review` + `ddev phpcs`/`ddev phpstan`) so review is incremental.

## Quality gates (run on `git diff <base>..HEAD`)
Run in this order; collect findings into a single report, present it, and only apply fixes after the user approves.
1. `ddev phpcs` and `ddev phpstan` on changed PHP / `.theme` / `.module` / `.install` files (skip if the diff has none).
2. `drupal-code-review` skill — Drupal standards/security/best practices (PHP).
3. `code-review` skill — correctness/bugs across the whole diff (incl. Twig/SCSS).
4. `simplify` skill and `php-refactor-scan` skill — reuse/simplification + refactor opportunities (report only).
5. **Compact QA notes** — 3–6 bullets: what changed + how to test, paste-ready for GitLab. Do NOT use the full
   `qa-notes` skill (too verbose).

**Accessibility (WCAG):** keep basic accessibility in mind throughout — `aria-label` on icon-only controls and
`<nav>` landmarks, `alt` text, labels tied to inputs, visible keyboard focus, and adequate colour contrast. No
formal audit; just don't ship the obvious regressions.

## Done criteria
- Acceptance criteria from the task verified (browser, desktop + mobile, permissions where relevant).
- No new PHP/JS errors (watchdog + browser console).
- Basic accessibility (WCAG) kept in mind — accessible names/labels, alt text, keyboard focus; no obvious regressions.
- Quality-gate findings resolved or explicitly deferred with a reason.
- Theme changes synced to `starter-theme/`; nothing committed/pushed unless the user asks.
