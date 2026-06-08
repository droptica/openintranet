# openintranet-task flow & quality gates — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the PHP quality tooling (phpcs/phpstan config + DDEV wrappers) and the project-scoped `openintranet-task` guide skill that standardizes task execution + review.

**Architecture:** Config files at repo root drive `vendor/bin` tools already installed via `drupal/core-dev` (coder sniffs) and `mglaman/phpstan-drupal` (auto-registered via `phpstan/extension-installer`). DDEV `web` commands wrap them with path args. The skill is an instructional `SKILL.md` that orchestrates existing skills through human-gated phases.

**Tech Stack:** Drupal 11, DDEV, PHP 8.3; `squizlabs/php_codesniffer` + `drupal/coder` (Drupal/DrupalPractice); `phpstan/phpstan` + `mglaman/phpstan-drupal`; Claude Code skills (Markdown).

**Spec:** `docs/superpowers/specs/2026-06-08-openintranet-task-flow-design.md`

**Branch:** `chore/openintranet-task-flow` (already created from `1.9.x`).

---

## File structure

- Create `phpcs.xml` — phpcs ruleset (Drupal + DrupalPractice) scoped to custom code.
- Create `phpstan.neon` — phpstan config, level 2, Drupal-aware, scoped to custom code.
- Create `.ddev/commands/web/phpcs` — DDEV wrapper, optional path args.
- Create `.ddev/commands/web/phpstan` — DDEV wrapper, optional path args.
- Create `.claude/skills/openintranet-task/SKILL.md` — the guide skill.

Scanned paths for both tools: `web/modules/custom` and `starter-theme/openintranet_theme` (the tracked theme
source; `web/themes/custom/` is the gitignored runtime copy).

---

## Task 1: phpcs ruleset

**Files:**
- Create: `phpcs.xml`

- [ ] **Step 1: Create `phpcs.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<ruleset name="openintranet">
  <description>Drupal coding standards for Open Intranet custom code.</description>

  <!-- Default scan targets (tracked custom code only). -->
  <file>web/modules/custom</file>
  <file>starter-theme/openintranet_theme</file>

  <!-- PHP-ish files only; never lint compiled assets or deps. -->
  <arg name="extensions" value="php,module,inc,install,theme,profile,engine"/>
  <arg name="parallel" value="8"/>
  <arg name="colors"/>
  <arg value="sp"/>

  <exclude-pattern>*/node_modules/*</exclude-pattern>
  <exclude-pattern>*/css/*</exclude-pattern>
  <exclude-pattern>*/js/*</exclude-pattern>
  <exclude-pattern>*/dist/*</exclude-pattern>
  <exclude-pattern>*.min.*</exclude-pattern>

  <rule ref="Drupal"/>
  <rule ref="DrupalPractice"/>
</ruleset>
```

- [ ] **Step 2: Verify phpcs loads the ruleset**

Run: `ddev exec ./vendor/bin/phpcs -i`
Expected: output lists `Drupal` and `DrupalPractice` among installed standards.

- [ ] **Step 3: Commit**

```bash
git add phpcs.xml
git commit -m "Add phpcs ruleset (Drupal + DrupalPractice) for custom code"
```

---

## Task 2: phpstan config

**Files:**
- Create: `phpstan.neon`

- [ ] **Step 1: Create `phpstan.neon`**

`mglaman/phpstan-drupal` is auto-registered by `phpstan/extension-installer`, so do NOT manually `include`
its neon files (double registration errors). Just set parameters:

```neon
parameters:
	level: 2
	paths:
		- web/modules/custom
		- starter-theme/openintranet_theme
	fileExtensions:
		- php
		- module
		- inc
		- install
		- theme
		- profile
	excludePaths:
		- */node_modules/*
		- */dist/*
	drupal:
		drupal_root: web/
	ignoreErrors:
		# Theme/.module procedural hooks are not classes; allow undefined-ish dynamic Drupal calls at low level.
		- '#Plugin .* not found#'
```

- [ ] **Step 2: Verify phpstan runs and is Drupal-aware**

Run: `ddev exec ./vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=-1 starter-theme/openintranet_theme/openintranet_theme.theme`
Expected: it completes (PASS with 0 errors, or a small list of real level-2 findings) and does NOT error with
"Extension already registered" or "Unknown parameter drupal". If it errors on the `ignoreErrors` regex being
unused, remove that `ignoreErrors` block.

- [ ] **Step 3: Adjust if needed, then commit**

If Step 2 shows config errors, fix inline (most likely: drop `ignoreErrors`, or correct `drupal_root`). Re-run until clean.

```bash
git add phpstan.neon
git commit -m "Add phpstan config (Drupal, level 2) for custom code"
```

---

## Task 3: DDEV wrapper commands

**Files:**
- Create: `.ddev/commands/web/phpcs`
- Create: `.ddev/commands/web/phpstan`

- [ ] **Step 1: Create `.ddev/commands/web/phpcs`**

```bash
#!/bin/bash
## Description: Run Drupal phpcs on custom code (optional path args)
## Usage: phpcs [path ...]
## Example: "ddev phpcs" or "ddev phpcs web/modules/custom/foo/foo.module"
cd /var/www/html || exit 1
./vendor/bin/phpcs --standard=phpcs.xml "$@"
```

- [ ] **Step 2: Create `.ddev/commands/web/phpstan`**

```bash
#!/bin/bash
## Description: Run Drupal phpstan (level 2) on custom code (optional path args)
## Usage: phpstan [path ...]
## Example: "ddev phpstan" or "ddev phpstan starter-theme/openintranet_theme/openintranet_theme.theme"
cd /var/www/html || exit 1
./vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=-1 "$@"
```

- [ ] **Step 3: Make executable + verify DDEV registers them**

```bash
chmod +x .ddev/commands/web/phpcs .ddev/commands/web/phpstan
```
Run: `ddev phpcs -i` (or `ddev phpcs --version`)
Expected: phpcs runs inside the container (no "command not found").
Run: `ddev phpstan --version`
Expected: prints PHPStan version.

- [ ] **Step 4: Commit**

```bash
git add .ddev/commands/web/phpcs .ddev/commands/web/phpstan
git commit -m "Add ddev phpcs/phpstan wrapper commands"
```

---

## Task 4: Smoke-test tooling on existing custom code

**Files:** none (verification only).

- [ ] **Step 1: Run phpcs on the custom module + theme source**

Run: `ddev phpcs`
Expected: completes; prints either "no errors" or a finite list of sniff violations. Capture the output — this
is the real baseline the skill will use. Do NOT auto-fix here.

- [ ] **Step 2: Run phpstan on the theme source**

Run: `ddev phpstan starter-theme/openintranet_theme`
Expected: completes at level 2 with a finite (likely small) list or zero errors. If it crashes on bootstrap,
fix `phpstan.neon` `drupal.drupal_root` and re-run.

- [ ] **Step 3: No commit** (verification only).

---

## Task 5: The `openintranet-task` skill

**Files:**
- Create: `.claude/skills/openintranet-task/SKILL.md`

- [ ] **Step 1: Create `.claude/skills/openintranet-task/SKILL.md`**

```markdown
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

## Done criteria
- Acceptance criteria from the task verified (browser, desktop + mobile, permissions where relevant).
- No new PHP/JS errors (watchdog + browser console).
- Quality-gate findings resolved or explicitly deferred with a reason.
- Theme changes synced to `starter-theme/`; nothing committed/pushed unless the user asks.
```

- [ ] **Step 2: Verify the skill is discoverable**

Run: `ls .claude/skills/openintranet-task/SKILL.md`
Expected: file exists. (In a new session the skill should appear in the available-skills list; the YAML
frontmatter `name`/`description` must be present and valid.)

- [ ] **Step 3: Commit**

```bash
git add .claude/skills/openintranet-task/SKILL.md
git commit -m "Add openintranet-task guide skill"
```

---

## Self-review (done while writing)
- Spec coverage: deliverables (skill ✓ Task 5, phpcs ✓ T1, phpstan ✓ T2, ddev wrappers ✓ T3, level 2 ✓ T2,
  5 gates incl. php-refactor-scan ✓ skill, compact QA ✓ skill, cadence ✓ skill, scan-3-tasks = separate execution
  referenced in spec §6). Covered.
- Placeholders: none — full file contents provided; verification steps have concrete commands/expected output.
- Consistency: tool paths (`web/modules/custom`, `starter-theme/openintranet_theme`) identical across phpcs.xml,
  phpstan.neon, and skill text.

## Not in this plan (separate execution)
Scanning the 3 existing task branches (3592728/3592730/3592733) — runs the same gates per branch as a follow-up
once tooling exists (spec §6).
