#!/usr/bin/env node

/**
 * create-reference.js
 * 
 * Membuat 3 reference files yang ringkas dari knowledge graph:
 * 1. reference-architecture.json - struktur high-level untuk overview
 * 2. reference-functions.json - peta function per file
 * 3. reference-dependencies.json - who imports who
 * 
 * Cara pakai:
 *   node scripts/create-reference.js
 * 
 * Output:
 *   .understand-anything/reference-*.json (3 files)
 */

import { existsSync, readFileSync, writeFileSync, statSync } from 'fs';
import { join } from 'path';

const graphPath = join(process.cwd(), '.understand-anything', 'knowledge-graph.json');

if (!existsSync(graphPath)) {
  console.error('❌ knowledge-graph.json tidak ditemukan di .understand-anything/');
  process.exit(1);
}

try {
  console.log('\n⏳ Loading graph and generating reference files...\n');
  
  const graph = JSON.parse(readFileSync(graphPath, 'utf8'));
  
  // ========================================
  // REFERENCE 1: ARCHITECTURE
  // ========================================
  
  const architectureRef = {
    generatedAt: new Date().toISOString(),
    project: 'Musik KITA',
    summary: `${graph.nodes?.length || 0} nodes, ${graph.edges?.length || 0} relationships`,
    
    // Group files by top-level directory
    layers: {},
    
    // Key statistics
    statistics: {
      totalFiles: 0,
      totalFunctions: 0,
      totalClasses: 0,
      totalImports: 0
    }
  };
  
  // Count by directory (layer)
  const files = (graph.nodes || []).filter(n => n.type === 'file');
  const functions = (graph.nodes || []).filter(n => n.type === 'function');
  const classes = (graph.nodes || []).filter(n => n.type === 'class');
  const imports = (graph.edges || []).filter(e => 
    e.type === 'imports' || e.type === 'import' || e.type === 'requires'
  );
  
  architectureRef.statistics.totalFiles = files.length;
  architectureRef.statistics.totalFunctions = functions.length;
  architectureRef.statistics.totalClasses = classes.length;
  architectureRef.statistics.totalImports = imports.length;
  
  // Group files by layer
  files.forEach(file => {
    const parts = file.id.split('/');
    const layer = parts[0]; // top-level directory
    
    if (!architectureRef.layers[layer]) {
      architectureRef.layers[layer] = {
        description: getLayerDescription(layer),
        files: [],
        fileCount: 0,
        nodeCount: 0
      };
    }
    
    architectureRef.layers[layer].files.push(file.id);
    architectureRef.layers[layer].fileCount += 1;
    architectureRef.layers[layer].nodeCount += 
      (graph.nodes || []).filter(n => n.fileId === file.id).length;
  });
  
  // Save architecture reference
  const archPath = join(process.cwd(), '.understand-anything', 'reference-architecture.json');
  writeFileSync(archPath, JSON.stringify(architectureRef, null, 2));
  
  console.log('✅ Created: reference-architecture.json');
  console.log(`   - Layers: ${Object.keys(architectureRef.layers).length}`);
  console.log(`   - Files: ${architectureRef.statistics.totalFiles}`);
  
  // ========================================
  // REFERENCE 2: FUNCTION MAP
  // ========================================
  
  const functionMap = {};
  let totalFunctionsProcessed = 0;
  
  (graph.nodes || []).filter(n => n.type === 'function').forEach(fn => {
    const fileId = fn.fileId;
    if (!functionMap[fileId]) {
      functionMap[fileId] = [];
    }
    functionMap[fileId].push({
      name: fn.label || fn.id,
      id: fn.id,
      line: fn.line || 'unknown'
    });
    totalFunctionsProcessed += 1;
  });
  
  const funcMapPath = join(process.cwd(), '.understand-anything', 'reference-functions.json');
  writeFileSync(funcMapPath, JSON.stringify(functionMap, null, 2));
  
  console.log('✅ Created: reference-functions.json');
  console.log(`   - Files with functions: ${Object.keys(functionMap).length}`);
  console.log(`   - Total functions: ${totalFunctionsProcessed}`);
  
  // ========================================
  // REFERENCE 3: DEPENDENCY MAP
  // ========================================
  
  const dependencyMap = {};
  let totalDepsProcessed = 0;
  
  (graph.edges || []).filter(e => 
    e.type === 'imports' || e.type === 'import' || e.type === 'requires'
  ).forEach(edge => {
    const from = edge.source;
    if (!dependencyMap[from]) {
      dependencyMap[from] = [];
    }
    dependencyMap[from].push({
      to: edge.target,
      type: edge.type
    });
    totalDepsProcessed += 1;
  });
  
  const depMapPath = join(process.cwd(), '.understand-anything', 'reference-dependencies.json');
  writeFileSync(depMapPath, JSON.stringify(dependencyMap, null, 2));
  
  console.log('✅ Created: reference-dependencies.json');
  console.log(`   - Modules with dependencies: ${Object.keys(dependencyMap).length}`);
  console.log(`   - Total dependencies: ${totalDepsProcessed}`);
  
  // ========================================
  // REFERENCE 4: QUICK INDEX (bonus)
  // ========================================
  
  const quickIndex = {
    generatedAt: new Date().toISOString(),
    
    // Quick search index
    classIndex: {},
    functionIndex: {},
    fileIndex: {}
  };
  
  // Index classes
  (graph.nodes || []).filter(n => n.type === 'class').forEach(cls => {
    quickIndex.classIndex[cls.label || cls.id] = {
      id: cls.id,
      file: cls.fileId,
      namespace: cls.namespace || 'unknown'
    };
  });
  
  // Index first 100 functions
  (graph.nodes || [])
    .filter(n => n.type === 'function')
    .slice(0, 100)
    .forEach(fn => {
      quickIndex.functionIndex[fn.label || fn.id] = {
        id: fn.id,
        file: fn.fileId
      };
    });
  
  // Index files
  files.forEach(file => {
    quickIndex.fileIndex[file.id] = {
      path: file.id,
      language: file.id.split('.').pop()
    };
  });
  
  const indexPath = join(process.cwd(), '.understand-anything', 'reference-index.json');
  writeFileSync(indexPath, JSON.stringify(quickIndex, null, 2));
  
  console.log('✅ Created: reference-index.json (bonus)');
  console.log(`   - Classes indexed: ${Object.keys(quickIndex.classIndex).length}`);
  console.log(`   - Functions indexed (first 100): ${Object.keys(quickIndex.functionIndex).length}`);
  
  // ========================================
  // CREATE MARKDOWN GUIDE
  // ========================================
  
  const guideContent = `# Musik KITA Knowledge Graph Reference Guide

Generated: ${new Date().toISOString()}

## Overview

This folder contains compressed, token-efficient references of your Musik KITA codebase extracted from the knowledge graph.

**Stats:**
- Total Files: ${architectureRef.statistics.totalFiles}
- Total Functions: ${architectureRef.statistics.totalFunctions}
- Total Classes: ${architectureRef.statistics.totalClasses}
- Total Dependencies: ${architectureRef.statistics.totalImports}

## Files in This Reference

### 1. reference-architecture.json
**Purpose:** High-level structure overview
**Use when:** You need to understand the overall architecture or find which layer a feature belongs to
**Size:** Small (~5-10KB) - safe to include in all prompts
**Format:** Directory-based grouping with file counts and node counts per layer

**Example usage in Cursor:**
\`\`\`
I need to add a new payment feature. Based on the architecture reference,
which layer should I add this to?
\`\`\`

### 2. reference-functions.json
**Purpose:** Map of all functions/methods and which file they're in
**Use when:** You need to find where a specific function is defined
**Size:** Medium (~20-50KB) - reference as needed
**Format:** File path → Array of functions in that file

**Example usage in Cursor:**
\`\`\`
Where is the enrollStudent function defined?
Check reference-functions.json for the exact file.
\`\`\`

### 3. reference-dependencies.json
**Purpose:** Dependency graph (who imports who)
**Use when:** You need to understand module dependencies or trace import chains
**Size:** Medium (~15-30KB) - reference as needed
**Format:** Module → Array of modules it imports

**Example usage in Cursor:**
\`\`\`
I'm refactoring EnrollmentService. What other modules depend on it?
Check reference-dependencies.json for incoming dependencies.
\`\`\`

### 4. reference-index.json
**Purpose:** Quick searchable index of classes and functions (first 100)
**Use when:** You need to quickly find if something exists
**Size:** Very small (~5-10KB) - safe in prompts
**Format:** Name-indexed objects for O(1) lookup

## Workflow: How to Use These References

### Workflow 1: Onboarding a New Feature
\`\`\`
1. Start with: reference-architecture.json
   ↓ Understand which layer(s) are affected
2. Use: reference-functions.json
   ↓ Find existing similar functions
3. Use: reference-dependencies.json
   ↓ Understand what else might be impacted
4. Check: reference-index.json
   ↓ Verify similar classes exist
5. Then: Ask for specific code snippets (not full files)
\`\`\`

### Workflow 2: Debugging an Issue
\`\`\`
1. Use: reference-index.json
   ↓ Find related classes/functions
2. Use: reference-dependencies.json
   ↓ Trace where data flows
3. Use: reference-functions.json
   ↓ Find specific function locations
4. Then: Pull exact code snippets for deep analysis
\`\`\`

### Workflow 3: Refactoring
\`\`\`
1. Use: reference-dependencies.json
   ↓ Find all modules that need updates
2. Use: reference-functions.json
   ↓ List all functions that need changes
3. Use: reference-architecture.json
   ↓ Ensure refactor respects layer structure
4. Then: Request refactoring with confidence
\`\`\`

## Token Savings

**Without these references (old way):**
- Full codebase: ~${Math.round((statSync(graphPath).size / 1024) * 4)} tokens per request

**With these references (new way):**
- reference-* files: ~5-15KB = 1,000-4,000 tokens
- Selective code snippets: ~20-50KB = 5,000-12,500 tokens
- **Total: 6,000-16,500 tokens per request**
- **Savings: ~75-85% per request**

## CLI Commands for Querying the Graph

Still have the original knowledge-graph.json? Use these commands for advanced queries:

\`\`\`bash
# Find all functions related to "Enrollment"
node scripts/extract-graph.js --query functions --filter "Enrollment"

# Find all files in app/Http directory
node scripts/extract-graph.js --query files --filter "app/Http"

# Find what imports StudentModel
node scripts/extract-graph.js --query relationships --filter "StudentModel"

# Get directory structure overview
node scripts/extract-graph.js --query structure
\`\`\`

## Integration with Cursor

### Option A: Include in .cursor/rules/
Create or edit \`.cursor/rules/music-kita-kg.mdc\`:

\`\`\`markdown
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
\`\`\`

### Option B: Copy-paste in Chat
For quick queries, just paste one of the reference files into Cursor chat. They're all under 50KB.

## When to Re-generate These References

After running \`/understand\` in Cursor to update the knowledge graph:

\`\`\`bash
node scripts/create-reference.js
\`\`\`

Takes ~5 seconds, safe to do frequently.

## Next Steps

1. ✅ Verify all 4 JSON files exist in .understand-anything/
2. ✅ Test with: \`node scripts/extract-graph.js --query structure\`
3. ✅ Add to Cursor rules or use in prompts
4. ✅ Track token usage and savings in your prompts

---

**Questions?** Check the extract-graph.js script for more query examples.
`;
  
  const guidePath = join(process.cwd(), '.understand-anything', 'REFERENCE-GUIDE.md');
  writeFileSync(guidePath, guideContent);
  
  console.log('✅ Created: REFERENCE-GUIDE.md (usage guide)');
  
  // SUMMARY
  console.log('\n' + '='.repeat(70));
  console.log('\n✅ ALL REFERENCE FILES GENERATED');
  console.log('\n📁 Files created in .understand-anything/:');
  console.log('   1. reference-architecture.json');
  console.log('   2. reference-functions.json');
  console.log('   3. reference-dependencies.json');
  console.log('   4. reference-index.json');
  console.log('   5. REFERENCE-GUIDE.md');
  console.log('\n📖 Read REFERENCE-GUIDE.md for usage instructions');
  console.log('\n⏭️  Next: Integrate into .cursor/rules/ or use in prompts');
  console.log('\n' + '='.repeat(70) + '\n');
  
} catch (error) {
  console.error('❌ ERROR:', error.message);
  console.error(error.stack);
  process.exit(1);
}

// Helper function to describe layers
function getLayerDescription(layerName) {
  const descriptions = {
    'app': 'Application logic (Controllers, Models, Services, Middleware)',
    'routes': 'Route definitions (API and web routes)',
    'database': 'Database migrations and seeders',
    'resources': 'API resource classes',
    'config': 'Configuration files',
    'storage': 'Storage and cache directories',
    'tests': 'Unit and feature tests',
    'vendor': 'Composer dependencies',
    'node_modules': 'NPM dependencies',
    'public': 'Public assets',
    'views': 'Blade templates (legacy)',
    'bootstrap': 'Bootstrap files (service providers)',
  };
  return descriptions[layerName] || 'Custom layer';
}
