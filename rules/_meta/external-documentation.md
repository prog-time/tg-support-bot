# External Documentation

> Purpose: Point AI agents to the public-facing documentation site source, which lives outside this repository.
> Context: Read this file before editing or referencing user-facing docs (VitePress site content), or when deciding whether a doc change belongs in `rules/` or in the docs site.
> Version: 1.0

---

## 1. Core Principle

- The public documentation site for TG Support Bot (`docs.tg-support-bot.ru`) is a separate git repository, not a subdirectory of this project
- Its source lives in a sibling directory on disk, next to this project's own directory: `../docs.tg-support-bot.ru`
- Its remote is `prog-time/tg-support-bot-docs` on GitHub
- It is a VitePress site (`npm run dev` / `npm run build` inside that repo)

## 2. Scope Separation

- `rules/` in this repository documents internal architecture, business rules, and coding standards **for AI agents working on this codebase**
- `../docs.tg-support-bot.ru` documents **the product, for end users and integrators** (e.g. External Sources API usage, setup guides)
- Never merge the two: do not write user-facing guide content into `rules/`, and do not write internal architecture rules into the docs site

```markdown
✅ Correct
- Internal rule about External Sources token generation → rules/domain/external-sources.md
- Public guide on how to call the External Sources API as an integrator → ../docs.tg-support-bot.ru

❌ Incorrect
- Writing a step-by-step "how to integrate" tutorial inside rules/domain/external-sources.md
```

## 3. Working Across the Two Repositories

- The docs site repo may not be checked out at `../docs.tg-support-bot.ru` in every environment — verify the path exists (`ls ../docs.tg-support-bot.ru`) before assuming it is present
- Changes to the docs site are a separate commit/PR in its own repository — never bundle them into an `issues-{N}` commit in this repository
- If a task requires updating both the internal rule and the public doc, state this explicitly and treat them as two independent changes

## Checklist

- [ ] Confirmed whether the change belongs in `rules/` (internal) or `../docs.tg-support-bot.ru` (public)
- [ ] Verified the sibling docs repo path exists before editing it
- [ ] Did not bundle docs-site changes into this repository's commits
