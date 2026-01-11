---
layout: default
title: Maker Commands
nav_order: 3
has_children: true
---

# Maker Commands

Complete reference for all 19 maker commands.

## Command Categories

### Domain Layer
- `make:hexagonal:entity` - Pure PHP entities
- `make:hexagonal:value-object` - Immutable value objects
- `make:hexagonal:exception` - Business exceptions
- `make:hexagonal:domain-event` - Domain events

### Application Layer
- `make:hexagonal:command` - CQRS commands + handlers
- `make:hexagonal:query` - CQRS queries + handlers + responses
- `make:hexagonal:repository` - Repository port + adapter
- `make:hexagonal:input` - Input DTOs
- `make:hexagonal:use-case` - Use cases

### UI Layer
- `make:hexagonal:controller` - Web controllers
- `make:hexagonal:form` - Symfony forms
- `make:hexagonal:cli-command` - Console commands

### Infrastructure Layer
- `make:hexagonal:message-handler` - Async message handlers
- `make:hexagonal:event-subscriber` - Event subscribers

### Testing
- `make:hexagonal:use-case-test` - Use case tests
- `make:hexagonal:controller-test` - Controller tests
- `make:hexagonal:cli-command-test` - CLI command tests
- `make:hexagonal:test-config` - Test configuration

### Rapid Development
- `make:hexagonal:crud` - Complete CRUD module (20+ files)

[See detailed documentation →](commands.md)
