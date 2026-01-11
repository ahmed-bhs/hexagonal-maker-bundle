---
layout: default
title: Changelog
parent: About
nav_order: 1
---

# Changelog

All notable changes to Hexagonal Maker Bundle are documented here.

---

## [Unreleased]

### Added
- Complete documentation site with MkDocs Material
- Comprehensive FAQ
- Step-by-step tutorials

---

## [2.0.0] - 2025-01-XX

### Added
- **NEW:** `make:hexagonal:crud` - Generate complete CRUD module (30+ files)
- **NEW:** `make:hexagonal:domain-event` - Generate domain events
- **NEW:** `make:hexagonal:event-subscriber` - Generate event subscribers
- **NEW:** `make:hexagonal:message-handler` - Generate async message handlers
- **NEW:** `make:hexagonal:use-case-test` - Generate use case tests
- **NEW:** `make:hexagonal:controller-test` - Generate controller tests
- **NEW:** `make:hexagonal:cli-command-test` - Generate CLI command tests
- **NEW:** `make:hexagonal:test-config` - Generate test configuration
- `--with-tests` option for all makers
- `--with-factory` option for command maker
- `--with-use-case` option for CLI command maker
- `--with-workflow` option for controller maker
- `--with-id-vo` option for entity and CRUD makers
- Symfony 7.x support

### Changed
- Improved YAML mapping templates
- Enhanced Doctrine integration guide
- Better error messages and validation
- Updated documentation structure

### Fixed
- Repository interface namespace resolution
- YAML mapping indentation
- Service autowiring configuration

---

## [1.0.0] - 2024-XX-XX

### Added
- Initial release
- 11 core maker commands:
  - `make:hexagonal:entity`
  - `make:hexagonal:value-object`
  - `make:hexagonal:exception`
  - `make:hexagonal:repository`
  - `make:hexagonal:command`
  - `make:hexagonal:query`
  - `make:hexagonal:use-case`
  - `make:hexagonal:controller`
  - `make:hexagonal:form`
  - `make:hexagonal:cli-command`
  - `make:hexagonal:input`
- YAML mapping for pure domain entities
- CQRS pattern support
- Symfony 6.4+ support
- PHP 8.1+ requirement

---

## Version History

| Version | Release Date | Highlights |
|---------|--------------|------------|
| **2.0.0** | 2025-01 | CRUD maker, Tests, Events |
| **1.0.0** | 2024-XX | Initial release |

---

## Upgrade Guides

### Upgrading to 2.0

No breaking changes! All 1.x code continues to work.

**New features:**
- Try `make:hexagonal:crud` for rapid development
- Add `--with-tests` to generate tests automatically
- Use domain events with `make:hexagonal:domain-event`

### Upgrading to 1.0

Initial release - no upgrade needed.

---

## Roadmap

### Planned Features

#### v2.1 (Q1 2025)
- [ ] GraphQL adapter generator
- [ ] API Platform integration
- [ ] Custom validation rules generator
- [ ] Specification pattern support

#### v2.2 (Q2 2025)
- [ ] Microservices support (gRPC, REST)
- [ ] Event Sourcing generators
- [ ] SAGA pattern support
- [ ] Multi-tenancy templates

#### v3.0 (Future)
- [ ] Visual module designer
- [ ] AI-powered code suggestions
- [ ] Performance analyzers
- [ ] Architecture validation tools

---

## Contributing

Want to help shape the future? See [Contributing Guide](../contributing/overview.md).

---

## Release Notes

### How We Version

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR** (X.0.0) - Breaking changes
- **MINOR** (x.X.0) - New features (backward compatible)
- **PATCH** (x.x.X) - Bug fixes

### Support Policy

| Version | PHP | Symfony | Support Status |
|---------|-----|---------|----------------|
| **2.x** | 8.1+ | 6.4+, 7.x | ✅ Active |
| **1.x** | 8.1+ | 6.4+ | ⚠️ Security only |

---

## Changelog Guidelines

When contributing, please add entries under `[Unreleased]` section following this format:

```markdown
### Added
- New feature description

### Changed
- Changed feature description

### Fixed
- Bug fix description

### Deprecated
- Deprecated feature description

### Removed
- Removed feature description
```

---

**Full changelog:** [GitHub Releases](https://github.com/ahmed-bhs/hexagonal-maker-bundle/releases)
