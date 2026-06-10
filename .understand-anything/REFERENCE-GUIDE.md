# Musik KITA Knowledge Graph Reference Guide

Generated: 2026-06-09T08:05:09.076Z

## Overview

This folder contains compressed, token-efficient references of your Musik KITA codebase extracted from the knowledge graph.

**Stats:**
- Total Files: 496
- Total Functions: 499
- Total Classes: 249
- Total Dependencies: 791

## Files in This Reference

### 1. reference-architecture.json
**Purpose:** High-level structure overview
**Use when:** You need to understand the overall architecture or find which layer a feature belongs to
**Size:** Small (~5-10KB) - safe to include in all prompts
**Format:** Directory-based grouping with file counts and node counts per layer

**Example usage in Cursor:**
```
I need to add a new payment feature. Based on the architecture reference,
which layer should I add this to?
```

### 2. reference-functions.json
**Purpose:** Map of all functions/methods and which file they're in
**Use when:** You need to find where a specific function is defined
**Size:** Medium (~20-50KB) - reference as needed
**Format:** File path → Array of functions in that file

**Example usage in Cursor:**
```
Where is the enrollStudent function defined?
Check reference-functions.json for the exact file.
```

### 3. reference-dependencies.json
**Purpose:** Dependency graph (who imports who)
**Use when:** You need to understand module dependencies or trace import chains
**Size:** Medium (~15-30KB) - reference as needed
**Format:** Module → Array of modules it imports

**Example usage in Cursor:**
```
I'm refactoring EnrollmentService. What other modules depend on it?
Check reference-dependencies.json for incoming dependencies.
```

### 4. reference-index.json
**Purpose:** Quick searchable index of classes and functions (first 100)
**Use when:** You need to quickly find if something exists
**Size:** Very small (~5-10KB) - safe in prompts
**Format:** Name-indexed objects for O(1) lookup

## Workflow: How to Use These References

### Workflow 1: Onboarding a New Feature
```
1. Start with: reference-architecture.json
   ↓ Understand which layer(s) are affected
2. Use: reference-functions.json
   ↓ Find existing similar functions
3. Use: reference-dependencies.json
   ↓ Understand what else might be impacted
4. Check: reference-index.json
   ↓ Verify similar classes exist
5. Then: Ask for specific code snippets (not full files)
```

### Workflow 2: Debugging an Issue
```
1. Use: reference-index.json
   ↓ Find related classes/functions
2. Use: reference-dependencies.json
   ↓ Trace where data flows
3. Use: reference-functions.json
   ↓ Find specific function locations
4. Then: Pull exact code snippets for deep analysis
```

### Workflow 3: Refactoring
```
1. Use: reference-dependencies.json
   ↓ Find all modules that need updates
2. Use: reference-functions.json
   ↓ List all functions that need changes
3. Use: reference-architecture.json
   ↓ Ensure refactor respects layer structure
4. Then: Request refactoring with confidence
```

## Token Savings

**Without these references (old way):**
- Full codebase: ~4873 tokens per request

**With these references (new way):**
- reference-* files: ~5-15KB = 1,000-4,000 tokens
- Selective code snippets: ~20-50KB = 5,000-12,500 tokens
- **Total: 6,000-16,500 tokens per request**
- **Savings: ~75-85% per request**

## CLI Commands for Querying the Graph

Still have the original knowledge-graph.json? Use these commands for advanced queries:

```bash
# Find all functions related to "Enrollment"
node scripts/extract-graph.js --query functions --filter "Enrollment"

# Find all files in app/Http directory
node scripts/extract-graph.js --query files --filter "app/Http"

# Find what imports StudentModel
node scripts/extract-graph.js --query relationships --filter "StudentModel"

# Get directory structure overview
node scripts/extract-graph.js --query structure
```

## Integration with Cursor

### Option A: Include in .cursor/rules/
Create or edit `.cursor/rules/music-kita-kg.mdc`:

```markdown
# Musik KITA Knowledge Graph Reference

When answering questions about the codebase:
- Reference this knowledge graph data first (don't request full files)
- Use the layer structure to suggest proper placement
- Cross-check dependencies before proposing changes

{include .understand-anything/reference-architecture.json}
{include .understand-anything/reference-index.json}

For specific queries, use:
- reference-functions.json for function locations
- reference-dependencies.json for dependency analysis
```

### Option B: Copy-paste in Chat
For quick queries, just paste one of the reference files into Cursor chat. They're all under 50KB.

## When to Re-generate These References

After running `/understand` in Cursor to update the knowledge graph:

```bash
node scripts/create-reference.js
```

Takes ~5 seconds, safe to do frequently.

## Next Steps

1. ✅ Verify all 4 JSON files exist in .understand-anything/
2. ✅ Test with: `node scripts/extract-graph.js --query structure`
3. ✅ Add to Cursor rules or use in prompts
4. ✅ Track token usage and savings in your prompts

---

**Questions?** Check the extract-graph.js script for more query examples.
