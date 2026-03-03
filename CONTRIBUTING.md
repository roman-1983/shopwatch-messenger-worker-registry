# Contributing

Thanks for your interest in contributing to Messenger Worker Registry! This document covers the guidelines and workflow for contributing.

## Getting Started

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/your-username/shopwatch-messenger-worker-registry.git
   cd shopwatch-messenger-worker-registry
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Run the tests to make sure everything works:
   ```bash
   vendor/bin/phpunit
   ```

## Development Workflow

1. Create a branch from `main`:
   ```bash
   git checkout -b feat/my-feature
   ```
2. Make your changes
3. Write or update tests for your changes
4. Run the full test suite:
   ```bash
   vendor/bin/phpunit
   ```
5. Commit your changes following the [commit conventions](#commit-conventions)
6. Push your branch and open a pull request

## Commit Conventions

This project follows [Conventional Commits](https://www.conventionalcommits.org/). Every commit message must follow this format:

```
<type>(<optional scope>): <description>
```

### Types

| Type | Purpose | Example |
|---|---|---|
| `feat` | New feature | `feat: add worker uptime display` |
| `fix` | Bug fix | `fix: prevent duplicate index entries` |
| `docs` | Documentation changes | `docs: add configuration examples to README` |
| `test` | Adding or updating tests | `test: add edge case for expired workers` |
| `refactor` | Code restructuring (no behavior change) | `refactor: extract cache key generation` |
| `perf` | Performance improvement | `perf: batch cache reads in getAll()` |
| `style` | Code style (formatting, whitespace) | `style: fix indentation in services.yaml` |
| `build` | Build system or dependencies | `build: bump minimum PHP version to 8.3` |
| `ci` | CI/CD configuration | `ci: add PHPUnit step to GitHub Actions` |
| `chore` | Maintenance tasks | `chore: update .gitignore` |

### Rules

- Use the **imperative mood** in the description: "add feature" not "added feature"
- Keep the first line under **72 characters**
- Do not end the description with a period
- Reference issues when applicable: `fix: resolve heartbeat race condition (#12)`

## Coding Standards

- **PHP 8.2+** — use modern PHP features (readonly properties, named arguments, enums, etc.)
- **Strict types** — all files must declare `strict_types=1`
- **Final classes** — prefer `final` classes unless extension is explicitly intended
- **Type declarations** — use parameter types, return types, and property types everywhere
- **No abbreviations** — use clear, descriptive names

## Testing

- Every feature or bug fix must include tests
- Tests use PHPUnit with Symfony's `ArrayAdapter` for cache (no external services needed)
- Aim for meaningful assertions, not just coverage numbers
- Run the full suite before submitting:
  ```bash
  vendor/bin/phpunit
  ```

## Pull Request Guidelines

- Keep PRs focused — one feature or fix per PR
- Write a clear description of what the PR does and why
- Make sure all tests pass
- Update the README if your change affects usage or configuration

## Reporting Issues

When reporting a bug, please include:

- PHP and Symfony versions
- Cache adapter being used
- Steps to reproduce
- Expected vs actual behavior

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
