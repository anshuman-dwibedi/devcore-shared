# DevCore Shared Library

Reusable PHP and JavaScript core used by the DevCore Suite applications.

## Problem This Solves

When multiple business apps share the same backend utilities and UI foundation,
copy-paste causes drift, repeated bugs, and slow maintenance.

This library centralizes shared building blocks so all apps can evolve
consistently.

## Features

- Common backend classes for API responses, auth, validation, analytics, and QR
	code workflows
- Storage abstraction for local, S3, and R2
- Shared UI assets (`devcore.css`, `devcore.js`) and design tokens
- Minimal bootstrap with autoloading

## Tech Stack

- PHP
- JavaScript
- CSS

## Library Structure

```text
devcore-shared/
	backend/
	ui/
	utils/
	bootstrap.php
```

## Installation

Use as a Git submodule in each application repository:

```bash
git submodule add https://github.com/anshuman-dwibedi/devcore-shared.git core
git submodule update --init --recursive
```

## Usage

In app entry files:

```php
require_once __DIR__ . '/core/bootstrap.php';
```

For admin/api folders:

```php
require_once dirname(__DIR__) . '/core/bootstrap.php';
```

## Configuration Expectations

Apps should provide a project-level `config.php` (or equivalent) and keep
secrets out of version control.

Recommended tracked templates per app:

- `config.example.php`
- `.env.example`

## Security

- Do not commit real credentials
- Keep `config.php` and `.env` ignored
- Gitleaks workflow is included for secret scanning

## Related Repositories

- https://github.com/anshuman-dwibedi/estatecore
- https://github.com/anshuman-dwibedi/livestore
- https://github.com/anshuman-dwibedi/medibook
- https://github.com/anshuman-dwibedi/restrodesk
- https://github.com/anshuman-dwibedi/portfolio

## Maintainer

Anshuman Dwibedi



[Dev Agent] Automated update by agent.