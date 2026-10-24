---
name: github-repo-prep
description: Use when preparing, cleaning, or publishing a local project to GitHub — adding .gitignore/.gitattributes/README, scanning for secrets and large files, fixing repo blockers, initializing git, and pushing. Triggers on "make this repo good for GitHub", "prepare for GitHub", "upload this to my GitHub", "push this project".
---

# Prepare a Repository for GitHub

Turn a local project into a clean, safe, pushable GitHub repository. Work in
this order, verify at every step, and never destroy user data.

## Safety rules

- NEVER commit secrets, `.env` files, database dumps, logs, or user uploads.
- NEVER force-push, rewrite history, or delete ambiguous files without explicit
  permission.
- Commit and push only when the user asks you to publish.
- Prefer `git fetch` + merge over `--force`.
- When deleting, prefer obvious junk (`*.bak`, empty stray files, runtime
  artifacts, test uploads). When unsure, list the file and ask.
- GitHub rejects files over 100 MB and warns over 50 MB. Check sizes first.

## Step 1: Survey the project

Run these before changing anything:

```bash
git status --short
git remote -v
git log --oneline -5
```

Also inspect:

- File tree (exclude `.git`): find existing `README`, `.gitignore`,
  `.gitattributes`, `LICENSE`, lockfiles, and build config.
- Total size and files above 5 MB.
- The stack (package.json, composer.json, requirements.txt, go.mod, etc.) so
  ignore templates and verification commands match the project.
- Config files and code for hardcoded credentials (`config/`, `database.php`,
  `.env`, API clients).

If `.git` exists but is empty, git will report "not a repository". Initialize a
fresh repo (Step 5).

## Step 2: Remove junk and runtime artifacts

Typical removals — confirm they are not referenced first:

- Editor/OS junk: `.DS_Store`, `Thumbs.db`, `*.swp`
- Backups: `*.bak`, `*.old`, `*.orig`, `index.html.bak`
- Runtime state: logs, lock files, PID files, cache dirs, compiled output
- Database dumps (`*.sql`, `*.sql.gz`) in backup directories
- Test/personal uploads (`uploads/`, `storage/`) that should stay local
- Empty stray files and empty tool directories

Search before deleting media: a file may be referenced by templates or pages.

```bash
grep -rn "filename.ext" . --exclude-dir=.git
```

## Step 3: Scan for secrets

Search source and config for credential patterns:

```bash
git grep -n -I -E "(api[_-]?key|secret|passwd|password|token|bearer|BEGIN (RSA|OPENSSH|PRIVATE) KEY|AIza|sk_live|AKIA|ghp_|xox[baprs]-)" -- .
```

Also look for:

- `.env`, `.env.*`, `*.pem`, `*.key`, `*.p12`
- Database dumps under `backup/`, `dump/`, `db/`
- Hardcoded production domains/credentials in config and CORS allowlists
- Passwords or hashes seeded in SQL files

Do not print secret values. If a real credential is found, stop and tell the
user to rotate it before pushing. If it was already committed, history must be
cleaned with the user's approval.

## Step 4: Add the standard GitHub files

### `.gitignore`

Start from the stack's conventions, then add project-specific runtime paths.
Baseline:

```gitignore
# Environment / secrets
.env
.env.*
!.env.example

# User content and generated data
/uploads/*
!/uploads/.gitkeep
/backup/*
!/backup/.htaccess

# Runtime and temporary files
*.log
*.sql.gz
*.tmp
*.bak
*.old
*.orig
*.save
*.swp
*.swo
.backup.lock

# OS / editor
.DS_Store
Thumbs.db
desktop.ini
.idea/
.vscode/
*.iml
```

Keep directories the app needs (e.g. `uploads/.gitkeep`, `backup/.htaccess`).
Verify ignored/kept files:

```bash
git check-ignore -v path/to/file
git ls-files --others --exclude-standard
```

### `.gitattributes`

Normalize text and mark binaries:

```gitattributes
* text=auto eol=lf

*.php text
*.html text
*.css text
*.js text
*.json text
*.md text
*.sql text
*.svg text
.gitignore text
.gitattributes text

*.bat text eol=crlf
*.cmd text eol=crlf

*.mp3 binary
*.mp4 binary
*.webm binary
*.jpg binary
*.jpeg binary
*.png binary
*.gif binary
*.ico binary
*.gz binary
```

CRLF/LF warnings from `git add` are expected after adding this file.

### `README.md`

Include, in this order:

1. Project name and one-paragraph description
2. Features
3. Tech stack and required versions/extensions
4. Installation: clone, dependencies, database import, config, permissions
5. How to run (commands and URLs)
6. Default credentials (if seeded) with a "change immediately" warning
7. Project structure
8. Configuration/environment variable table
9. Operational notes (backups, jobs, large assets)
10. Security notes (HTTPS, credentials, deployment caveats)
11. License, only if the user chose one

Use real, verified commands. Do not claim tests or CI exist if they do not.

### Optional

- `LICENSE` only when the user picks one. Public repos without a license are
  "all rights reserved".
- `.env.example` only when the app actually loads env files.
- `.editorconfig`, CI workflows, `CONTRIBUTING.md` only if requested.

## Step 5: Fix blockers found during the survey

Small fixes that make a repo actually usable:

- Broken internal references (e.g. links to `invitation.php` while the file is
  `invitation1.php`): rename or update references.
- Duplicate/legacy config files: confirm usage with grep, then remove the unused
  one.
- Config credentials: read from environment variables with local defaults.
- Deny internal maintenance scripts to the web (Apache example):

  ```apache
  <FilesMatch "^(_.*\.php|schedule_backup\.bat)$">
    Require all denied
  </FilesMatch>
  ```

- Weak or broken seeded credentials: generate a fresh hash and verify it.

  ```bash
  php -r "echo password_hash('NewPassword', PASSWORD_BCRYPT, ['cost' => 12]), PHP_EOL;"
  php -r "var_dump(password_verify('NewPassword', 'HASH'));"
  ```

  Note: in PowerShell, single-quote hashes containing `$` to avoid expansion.

- Make helper scripts portable (`%~dp0` on Windows instead of absolute paths).

Then verify with the project's own commands. Common examples:

```bash
php -l file.php           # single PHP file
find . -name '*.php' -not -path './vendor/*' -exec php -l {} \;
npm test && npm run lint && npm run build
python -m pytest
go test ./...
```

## Step 6: Initialize and publish

Only when the user asks to publish:

```bash
git init -b main
git add -A
git commit -m "Initial commit"
git remote add origin https://github.com/<user>/<repo>.git
git push -u origin main
```

Before committing, run `git status`, `git diff --cached --stat`, and confirm
no secrets or unwanted binaries are staged.

### If the push is rejected

**"Repository not found"**: wrong URL, repo does not exist, or auth failed. Ask
the user to confirm the URL and authenticate (`gh auth login`).

**"Updates were rejected ... fetch first"**: the remote has commits. Fetch and
inspect before touching anything:

```bash
git fetch origin
git log --oneline --decorate --all -10
git ls-tree -r --name-only origin/main
```

If the remote only contains an auto-generated `README.md` and histories are
unrelated:

```bash
git merge origin/main --allow-unrelated-histories --no-edit
# resolve conflicts, keeping the project's own README
git checkout --ours -- README.md
git add README.md
git commit --no-edit
git push -u origin main
```

Do not force-push unless the user explicitly approves discarding remote work.

### If git refuses to commit

Set identity if missing:

```bash
git config --global user.name "Your Name"
git config --global user.email "you@example.com"
```

## Step 7: Final verification

```bash
git status -sb
git log --oneline -3
git remote -v
```

Report:

- The GitHub URL
- What was removed/added/fixed
- Excluded paths (uploads, backups, secrets)
- Repo size and any large media
- Remaining manual steps (set credentials, configure domain, add license)
- That the user must not commit runtime data later

## Common mistakes

- Committing `uploads/` or database dumps because a negation rule was missing.
- Adding `.opencode/` to `.gitignore` and then wondering why skills are not
  tracked — allow the skill paths or omit the ignore.
- Force-pushing over a remote README instead of merging unrelated histories.
- Claiming a default password works without running `password_verify`.
- Leaving broken internal links after renaming files.
- Deleting media or migration files that are still referenced.
