# How to Write Rules Files

> **Version:** 1.0.0
> **Context:** Read this file before creating or editing any file in the `rules/` directory.

---

## 1. Purpose of rules files

Rules files exist to give any AI agent — or human developer — an instant, reliable understanding of this project. Without them, every agent must re-read the entire codebase from scratch, risks making inconsistent decisions, and may repeat mistakes already solved by the team.

A good rules file answers three questions:
1. **What does this part of the system do?**
2. **What are the non-negotiable constraints?**
3. **What does correct look like vs. incorrect?**

---

## 2. Mandatory structure of every rules file

Every `.md` file in `rules/` must contain all of the following:

1. **A `# Title`** matching the file's subject exactly.
2. **A `> Context:` blockquote** at the top explaining when an AI agent must read this file.
3. **A `> Version: x.x` blockquote** in the header.
4. **Numbered sections** with clear, imperative headings (`## 1. Purpose`, `## 2. Rules`, etc.).
5. **At least one `✅ Correct` example and one `❌ Incorrect` example** per major rule.
6. **A `## Checklist` section** at the end of every file.

```markdown
✅ Correct structure:
# Domain: Messaging

> **Version:** 1.0.0
> **Context:** Read this file before modifying any message-routing logic.

## 1. What is this domain?
...

## 2. Business rules
...

## Checklist
- [ ] ...
```

```markdown
❌ Incorrect structure:
# Messaging stuff

Some notes about messages...
(no version, no context, no checklist, no examples)
```

---

## 3. Writing style rules

### 3.1 Language

Write in short, imperative sentences.

```markdown
✅ Correct:
> Always declare `strict_types=1` at the top of every PHP file.
> Never call `env()` outside `config/` files.
> Use DTOs for all data passed between layers.
```

```markdown
❌ Incorrect:
> It might be good to consider using strict types when possible.
> You could think about not using env() in some places.
> DTOs are probably a good idea here.
```

### 3.2 Person and voice

The agent must be addressed directly: "the agent must", "you must", "never do X".

```markdown
✅ Correct:
> The agent must run Pint before committing.
> You must create a test for every new public method.
```

```markdown
❌ Incorrect:
> One could run Pint.
> Tests are generally recommended.
```

### 3.3 Code examples

Every rule that can be misunderstood must have a code example.

```php
// ✅ Correct — DTO used between Service and Job
public function handle(TGTextMessageDto $dto): void
{
    SendTelegramMessageJob::dispatch($dto);
}

// ❌ Incorrect — raw array passed instead of DTO
public function handle(array $data): void
{
    SendTelegramMessageJob::dispatch($data);
}
```

**Example rules:**
- Every example must be syntactically correct and runnable.
- Label examples with `// ✅ Correct` and `// ❌ Incorrect`.
- Never use `// ... do something here` without showing the actual code.

---

## 4. Decomposition rules

- One rule per bullet point — never combine two rules in one sentence.
- If a section grows beyond 10 bullet points, split it into subsections.
- If a topic covers more than 3 distinct concerns, create a separate file.

```markdown
✅ Correct — one rule per bullet:
- Never pass raw arrays between layers.
- Always use DTOs.
- DTOs must extend `Spatie\LaravelData\Data`.
```

```markdown
❌ Incorrect — two rules in one sentence:
- Never pass raw arrays between layers and always use DTOs which must extend Data.
```

---

## 5. Versioning and maintenance

- Every rules file must have a `> Version: x.x` in the header blockquote.
- When a rule changes, update the version number and add a `## Changelog` section at the bottom.
- Never delete a rule — mark it as deprecated with `~~strikethrough~~` and note the replacement.

```markdown
✅ Correct deprecation:
~~Use `$request->all()` inside controllers.~~ _Deprecated in v1.1 — use FormRequest instead._
```

---

## 6. Forbidden content in rules files

- ❌ Opinions without justification — every rule must have a reason.
- ❌ Duplicate rules already defined in another file — link to that file instead.
- ❌ Broken or hypothetical code examples.
- ❌ Vague rules that cannot be verified (e.g. "write clean code", "be careful").
- ❌ Rules that contradict another file without explicitly noting the override.

---

## Checklist

- [ ] File has `# Title` matching its subject
- [ ] File has `> Context:` blockquote
- [ ] File has `> Version:` blockquote
- [ ] All sections are numbered with imperative headings
- [ ] Every major rule has `✅ Correct` and `❌ Incorrect` examples
- [ ] No vague, unverifiable rules
- [ ] File ends with `## Checklist` section
- [ ] No duplicate rules from other files — links used instead
