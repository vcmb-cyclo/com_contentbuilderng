# CBStats — Codex start here

## Repository location

Main repository:

`C:\Wamp\workspaces\com_contentbuilderng`

CBStats plugin:

`C:\Wamp\workspaces\com_contentbuilderng\plugins\content\contentbuilderng_cbstats`

## Recommended file placement

Copy the files from this package into the matching locations of the real repository.

Target structure (the root `AGENTS.md` already exists and remains unchanged):

```text
C:\Wamp\workspaces\com_contentbuilderng\
├── AGENTS.md                 # existing repository file — keep unchanged
├── Gil_CODEX_START_HERE.md
└── plugins\
    └── content\
        └── contentbuilderng_cbstats\
            ├── AGENTS.md
            └── docs\
                ├── Gil_CBSTATS_SPECIFICATION.md
                ├── Gil_CBSTATS_PUBLIC_API.md
                ├── Gil_PLUGIN_DESCRIPTION.md
                ├── Gil_01_JSON_PROVIDER.md
                ├── Gil_02_PIE.md
                ├── Gil_03_BAR.md
                ├── Gil_04_DOCUMENTATION_AND_API.md
                ├── Gil_05_TESTS_AND_ACCEPTANCE.md
                ├── Gil_PROMPT_CODEX_PASS_1_JSON.md
                ├── Gil_PROMPT_CODEX_PASS_2_PIE.md
                └── Gil_PROMPT_CODEX_PASS_3_BAR.md
```

## Root `AGENTS.md`: keep the existing file unchanged

The repository already contains a root `AGENTS.md`. It is authoritative and must **not** be replaced by this package.

It already defines the repository-wide constraints, including Joomla 6 only, PHP 8.3+, MySQL/MariaDB only, native Joomla 6 APIs, no legacy/deprecated compatibility mechanisms, minimal targeted changes, and synchronized translations in `en-GB`, `fr-FR` and `de-DE`.

This package therefore adds only the scoped CBStats `AGENTS.md` under the plugin directory plus the `Gil_*.md` specification and mission files.

## Recommended execution order

1. Install/merge the instruction and specification files.
2. Run **Pass 1 — JSON provider** only.
3. Review the implementation and run regression tests.
4. Update documentation for the completed pass.
5. Run **Pass 2 — Pie** only.
6. Review and test.
7. Run **Pass 3 — Bar** only.
8. Complete the cross-repository documentation/API update.

Do not implement JSON, Pie and Bar in one large pass.

## Source of truth priority

When instructions conflict, use this order:

1. explicit current user request;
2. nearest applicable `AGENTS.md`;
3. `Gil_CBSTATS_SPECIFICATION.md`;
4. the active mission document (`01_`, `02_`, `03_`);
5. existing CBStats public behavior for no-regression purposes;
6. historical prompts.

The historical prompts have been consolidated into the specification and mission files in this package.
