# Musik KITA Domain-Aware Reference Guide

Generated: 2026-06-09T08:06:07.259Z

## Overview

These references combine **Domain Graph** (business logic) + **Knowledge Graph** (code structure).

**Files:**
1. reference-domain-index.json - Full domain/flow/step structure with code locations
2. reference-domain-to-code-mapper.json - Entity → Code file mapping
3. reference-cross-domain-impact.json - What breaks when you change X?

## Workflow Examples

### Find domain and flows
```bash
node scripts/query-domain-code.js --domain "student-lifecycle" --list-steps
```

### Find entity and impact
```bash
node scripts/query-domain-code.js --entity "Student" --show-impact
```

### Search across domains
```bash
node scripts/query-domain-code.js --search "honor"
```

## For Cursor Integration

Create `.cursor/rules/musik-kita-domain.mdc` with:

```markdown
# Musik KITA Domain-Aware Development

When answering questions:
1. Check reference-domain-index.json first
2. Use exact filePath from steps
3. Reference reference-cross-domain-impact.json for side effects

{include .understand-anything/reference-domain-index.json}
```

See REFERENCE-DOMAIN-GUIDE.md for complete documentation.
