---
layout: default
title: User Registration
parent: Examples
nav_order: 1
---

# Example: User Registration Module

Complete example of building a User Registration module with hexagonal architecture.

---

## Overview

We'll build a user registration system with:
- Email validation
- Password hashing
- Duplicate email prevention
- Email confirmation workflow

---

## Step 1: Generate Structure

```bash
# Domain
bin/console make:hexagonal:entity user/account User --with-repository --with-id-vo
bin/console make:hexagonal:value-object user/account Email
bin/console make:hexagonal:exception user/account InvalidEmailException
bin/console make:hexagonal:exception user/account UserAlreadyExistsException

# Application
bin/console make:hexagonal:command user/account register --factory --with-tests
bin/console make:hexagonal:query user/account find-by-email

# UI
bin/console make:hexagonal:controller user/account RegisterUser /users/register --with-workflow
```

---

## Step 2: Complete Implementation

See full implementation in the [Quick Start Guide](../getting-started/quick-start.md).

---

## Complete Example

For a fully working example with all code, see the repository examples directory.
