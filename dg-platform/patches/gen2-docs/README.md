# Gen 2 documentation patches

This agent cannot push `dg-platform-web`. Copy these files into that repo:

| This file | Destination in `dg-platform-web` |
|-----------|----------------------------------|
| `ADR-0014-wordpress-legacy-connector-only.md` | `docs/adr/0014-wordpress-legacy-connector-only.md` |
| `AGENTS-BOUNDARY-SNIPPET.md` | Top of `AGENTS.md` (merge, don’t replace Next.js rules) |

Then tighten `docs/PLATFORM-PRINCIPLES.md` decision checklist with:

- [ ] DigitalGate platform functionality? If yes, this repo — not WordPress.
- [ ] Customer WP integration? Connector only.
- [ ] No temporary WP implementation to migrate later.

`docs/WP-DETACH-BACKLOG.md` remains the ticket list. ADR 0014 is the hard rule that backlog tickets must not violate.
