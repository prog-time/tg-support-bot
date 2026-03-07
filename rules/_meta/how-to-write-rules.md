# How to Write Rules

> **Purpose:** This file defines the standard every rules file in this project must meet. It is self-referential — it must itself comply with the rules it defines.
> **Context:** Read this file before creating or updating any rules in the `rules/` directory.
> **Version:** 1.0

---

## 1. Purpose

- Explain why this documentation system exists
- Describe what problem it solves
- Ensure consistency across all rules files
- Make onboarding for AI agents and developers deterministic

This system exists because AI agents working on the TG Support Bot codebase need a persistent, structured knowledge base that does not rely on code comments or memory. Without this, agents produce inconsistent, context-free, or hallucinated results.

---

## 2. Mandatory Structure of Every Rules File

Every `.md` file in `rules/` must contain:

1. A `# Title` matching the file's subject
2. A `> Context:` blockquote explaining when an AI agent should read this file
3. A `> Version:` line in the header
4. Numbered sections with clear, imperative headings
5. At least one `✅ Correct` and one `❌ Incorrect` example per major rule
6. A `## Checklist` section at the end

**Template:**

```markdown
# Title of the Rules File

> Purpose: What this file documents.
> Context: When an AI agent must read this file.
> Version: 1.0

## 1. Core Principle

...

## Checklist

- [ ] Item 1
- [ ] Item 2
```

---

## 3. Writing Style Rules

### Language

- Write all rules in English
- Write in short, imperative sentences: "Use X", "Never do Y", "Always Z"
- Avoid vague qualifiers: "try to", "consider", "might want to" — state rules definitively
- Use second person: "the agent must", "you must"

### Code Example Formatting

```php
// ✅ Correct
public function findById(int $id): ?BotUser
{
    return BotUser::find($id);
}

// ❌ Incorrect — missing return type, untyped parameter
public function findById($id)
{
    return BotUser::find($id);
}
```

### Decomposition Rules

- One rule per bullet point — never combine two rules in one sentence
- If a section grows beyond 10 bullets, split into subsections
- If a topic covers more than 3 distinct concerns, create a separate file

---

## 4. File Naming and Location Rules

| Rule | Example |
|---|---|
| Use lowercase, hyphenated names | `bot-users.md`, `external-sources.md` |
| Domain files go in `rules/domain/` | `rules/domain/messaging.md` |
| Process files go in `rules/process/` | `rules/process/security.md` |
| Database schema is one file | `rules/database/schema.md` |
| API contract is one file | `rules/api/endpoints.md` |

```
✅ Correct
rules/domain/bot-users.md

❌ Incorrect
rules/BotUsers.md
rules/domain/BotUsersRules.MD
```

---

## 5. Versioning and Changelog Rules

```markdown
// ✅ Correct
> Version: 1.1
## Changelog
- BR-005 updated to include edge case validation
```

```markdown
// ❌ Incorrect — no version, no changelog
```

- Increment version when any rule changes
- Add changelog entry with rule ID and description of change

---

## 6. Forbidden Content

- ❌ Opinions without justification — every rule must have a reason
- ❌ Duplicate rules that already exist in another file — link instead
- ❌ Broken or hypothetical code examples
- ❌ Vague rules that cannot be verified (e.g., "write clean code")
- ❌ Rules that contradict another file without explicitly noting the override
- ❌ Rules written in Russian (all rules must be in English)

---

## 7. Cross-References

When a rule depends on another file, link explicitly:

```markdown
// ✅ Correct
See also: `database/schema.md` for column constraints.
```

```markdown
// ❌ Incorrect — assumes agent knows where to look
As described elsewhere, the column is nullable.
```

---

## Checklist

- [ ] Title matches the file subject
- [ ] `> Context:` blockquote included
- [ ] `> Version:` included
- [ ] Numbered sections present
- [ ] At least one ✅ Correct and one ❌ Incorrect example per major rule
- [ ] Examples are syntactically correct
- [ ] Decomposition rules followed
- [ ] Forbidden content avoided
- [ ] File location follows naming conventions
