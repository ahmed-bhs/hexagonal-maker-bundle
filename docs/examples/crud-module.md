---
layout: default
title: CRUD Module
parent: Examples
nav_order: 2
---

# Example: CRUD Module

Generate complete CRUD module with one command.

---

## Quick CRUD Generation

```bash
bin/console make:hexagonal:crud blog/post Post --with-tests --with-id-vo --route-prefix=/posts
```

**This generates 30+ files:**

- Entity + Repository + ID ValueObject
- 5 Use Cases (Create, Update, Delete, Get, List)
- 5 Controllers + Form
- All tests

---

## Generated Routes

- `GET /posts` - List all posts
- `GET /posts/{id}` - Show single post
- `GET /posts/new` - Create form
- `POST /posts/new` - Submit new post
- `GET /posts/{id}/edit` - Edit form
- `POST /posts/{id}/edit` - Submit update
- `DELETE /posts/{id}/delete` - Delete post

---

## Next Steps

1. Complete Doctrine mapping
2. Configure module in `doctrine.yaml`
3. Generate migrations
4. Test your CRUD!

---

See [Quick Start](../getting-started/quick-start.md) for full tutorial.
