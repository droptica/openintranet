# Design — `openintranet-task` flow & quality gates

**Date:** 2026-06-08
**Status:** Approved design (pending spec review)
**Goal:** A repeatable, project-standard way to execute an Open Intranet task end-to-end, with
research → brainstorm → spec → plan → execute (Opus 4.8) → quality gates → fixes, so future tasks are
done consistently and reviewed before merge.

---

## 1. Deliverable

A project-scoped **guide skill** `openintranet-task` plus the **PHP tooling config** it relies on.

- Skill is **instructional** (a `SKILL.md`), not a Workflow: the flow has human gates (brainstorm,
  spec approval, findings approval) that an autonomous Workflow can't honor. The skill orchestrates the
  existing skills (`superpowers:brainstorming`, `superpowers:writing-plans`, subagent-driven execution,
  and the review skills) rather than re-implementing them.
- Location: `.claude/skills/openintranet-task/SKILL.md` (committed, team-shared via repo).

## 2. The flow (when given a task text or a Drupal.org work-item link)

1. **Intake** — reformat the task into `tasks/<id>.md` (link at top, branch name, English); create branch
   `issue/<id>-<slug>` from the base branch; ensure `/tasks/` is gitignored (local notes).
2. **Research** — explore the codebase (inline / Explore agents / Workflow when broad); write a
   "Current state analysis" section in `tasks/<id>.md`.
3. **Brainstorm** — `superpowers:brainstorming` → locked decisions.
4. **Spec** — written into `tasks/<id>.md` (decisions + plan); larger efforts may also use
   `docs/superpowers/specs/`.
5. **Plan** — `superpowers:writing-plans`.
6. **Execute** — subagent-driven implementation on **Opus 4.8**; compile (`ddev theme compile` / `gulp styles`
   for theme), verify in the browser (chrome-devtools MCP), and **sync changes to `starter-theme/openintranet_theme/`**
   (the runtime `web/themes/custom/` copy is gitignored).
7. **Quality gates** (on the branch diff) → **Fixes** (after approval) → commit on the same branch.

## 3. Quality gates

Run against `git diff <base>..HEAD` for the task branch, in this order:

1. **`ddev phpcs`** + **`ddev phpstan`** — only on changed PHP / `.theme` / `.module` / `.install` files. Fast, objective.
2. **`drupal-code-review`** — Drupal standards, security, best practices (PHP).
3. **`code-review`** — correctness/bugs across the whole diff (incl. Twig/SCSS).
4. **`simplify`** + **`php-refactor-scan`** — reuse/simplification + Fowler/PHP-8 refactor opportunities (report, not auto-applied).
5. **Compact QA notes** — 3–6 bullets (what was done + how to test), paste-ready for GitLab. NOT the full
   `qa-notes` skill (that produces walls of text).

**Gate behavior:** findings are collected into a **report**; after the user approves, fixes are applied as a
commit on the correct branch, then re-verified.

## 4. Cadence (scale to task size)

- **Small task** → run the quality gates **once at the end**, before declaring done.
- **Large / multi-stage task** → after each completed execute chunk, run the relevant gates on that chunk
  (typically `simplify` + `code-review` + `phpcs`/`phpstan`), so review is incremental rather than a big-bang at the end.

## 5. PHP tooling (one-time prerequisite setup)

`phpcs`, `phpcbf`, `phpstan` already exist in `vendor/bin` (via `drupal/core-dev`), but there is no project
config for custom code. Add:

- **`phpcs.xml`** — standards `Drupal` + `DrupalPractice`; paths: `web/modules/custom/`, `web/themes/custom/`
  (extensions `php,module,inc,install,theme,profile`); exclude compiled assets/`node_modules`.
- **`phpstan.neon`** — `mglaman/phpstan-drupal` extension, **level 2**, paths: `web/modules/custom/`,
  `web/themes/custom/` (PHP/.theme only).
- **DDEV wrappers** — `.ddev/commands/web/phpcs` and `.ddev/commands/web/phpstan` that run the binaries with
  the configs, accepting optional path args so the skill can scope to changed files.

> Note: `mglaman/phpstan-drupal` may need adding to `require-dev` if not already pulled in by core-dev — verify
> during implementation.

## 6. Scanning the 3 existing tasks (separate follow-up, after gates exist)

Each task branch already has exactly one commit above `1.9.x`:
`issue/3592728-unsupported-module`, `issue/3592730-improve-styling`, `issue/3592733-move-edit-links`.

Per branch: checkout → run the quality gates on `git diff 1.9.x..HEAD` → **report** → user approval →
fix-commit on that branch → re-verify.

> Reality check: these 3 are mostly SCSS/Twig/CSS with a little PHP (the `page` preprocess in 3592733;
> `composer.json` in 3592728). So `phpcs`/`phpstan`/`drupal-code-review` have little surface; `simplify` and
> `code-review` over the SCSS/Twig will be the most useful.

## 7. Out of scope
- No stylelint/Twig linter setup for now (theme builds via gulp; can be a later addition).
- The skill does not auto-merge or push; integration stays manual.
- Full `qa-notes` skill is intentionally not used (compact notes instead).

## 8. Branching for this work
- This meta-work (skill + tooling + this spec) lives on `chore/openintranet-task-flow` (from `1.9.x`).
- The 3 existing-task scans/fixes happen on their own `issue/*` branches.
