# Coding Standards (VelvetCMS Core)

This document defines the coding conventions for VelvetCMS Core.

For contribution workflow, see [CONTRIBUTING.md](CONTRIBUTING.md).

## Baseline

- **PHP**: 8.4+
- **Strict types**: every PHP file should start with `declare(strict_types=1);`
- **Style**: PSR-12 as the baseline (spacing/brace placement/structure)
- **Typing**: type all parameters and return values; prefer typed properties
- **Avoid `mixed`** unless you are at a boundary (I/O, config, framework entrypoints) or there is a clear reason

If a rule is unclear in a specific case, prefer the simplest code that keeps invariants explicit.

## Comments

We aim for readable code with the least amount of comments.

### When to write a comment

Write comments only when they add information that **is not already obvious from the code**:

- **Why / intent**: rationale, constraints, tradeoffs (especially when the code is intentionally surprising)
- **Security invariants**: ordering requirements or trust boundaries
- **Compatibility/workarounds**: non-obvious behavior required due to upstream limitations
- **Tricky algorithms/edge cases**: parsing rules, regexes, caching formats, ordering guarantees

### When *not* to write a comment

- Don’t restate types already expressed by the signature (no “returns array of …” when `: array` exists).
- Don’t add docblocks that mirror `string|int|array` types already in code.
- Don’t leave commented-out code in the repository (delete it).

### Style

- Keep comments short and specific (prefer 1–3 lines).
- Prefer placing comments directly above the block they explain.
- Prefer changing code (naming/extraction) over adding explanation.

## PHPDoc

PHPDoc is allowed, but only when it adds information PHP cannot express clearly. We prioritize **Clean Code** over redundant documentation.

### The "Zero Noise" Policy

1.  **Interfaces are Contracts**: Keep `@throws` annotations on interfaces. In a modular system without checked exceptions, the consumer must know what can fail.
2.  **Type Hints over DocBlocks**: Delete `@param` and `@return` tags if the type is fully expressed in the method signature.
    - *Exception*: Keep them for `array` content definition (e.g. `/** @return Page[] */`) or complex `mixed` types.
3.  **No "Echo" Comments**: Delete function descriptions that just translate the function name into English.
    - *Bad*: `/** Check if exists */ public function exists(...)`
    - *Good*: Delete the comment entirely.

Use PHPDoc for:
- **`@throws`** (Mandatory on Interfaces/Contracts)
- **Array shapes / collection types**
- **Deprecations** (`@deprecated`)

Avoid PHPDoc for:
- Redundant type repetition.
- "What" comments (use "Why" comments inside code instead).

## Tooling (Optional)

We don’t require tooling in the core workflow yet, but if we add it, the preferred direction is:

- **Formatter/Fixer**: auto-format to PSR-12 baseline (minimize style churn in reviews)
- **Static analysis**: catch type issues and dead code early (introduced gradually)

If you want to propose a tooling setup, include:

- A composer script (e.g. `composer lint`, `composer format`, `composer analyse`)
- Clear instructions for contributors
- A configuration file committed to the repo
