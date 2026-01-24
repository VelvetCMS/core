# Contributing to VelvetCMS

Thanks for your interest in contributing to VelvetCMS.

## Quick Start

1. **Clone and install**
   ```bash
   git clone https://github.com/VelvetCMS/VelvetCMS-Core.git
   cd VelvetCMS-Core
   composer install
   ```

2. **Bootstrap**
   ```bash
   ./velvet install
   ```

3. **Run tests**
   ```bash
   composer test
   ```

4. **Serve locally**
   ```bash
   ./velvet serve
   ```

## Code Standards

All code style and PHPDoc guidance lives in [CODING_STANDARDS.md](CODING_STANDARDS.md). Keep changes minimal and explicit, and follow the “Zero Noise” PHPDoc policy.

## Branching & Commits

- Branches: `feature/*`, `bugfix/*`, `hotfix/*`
- Commit messages: [Conventional Commits](https://www.conventionalcommits.org/)

## Pull Requests

1. Write focused changes with tests when possible.
2. Update docs when behavior changes.
3. Run `composer test` before pushing.

## Security

For security reports, see [SECURITY.md](SECURITY.md). Do not open public issues for vulnerabilities.

## License

VelvetCMS Core is Apache-2.0. By contributing, you agree your contributions are licensed under Apache-2.0 (inbound = outbound).