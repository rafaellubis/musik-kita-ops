#!/usr/bin/env node

/**
 * extract-graph.js
 * 
 * Query knowledge-graph.json untuk kasus spesifik tanpa load full codebase
 * 
 * Cara pakai:
 *   node scripts/extract-graph.js --query functions --filter "Enrollment"
 *   node scripts/extract-graph.js --query classes --limit 30
 *   node scripts/extract-graph.js --query relationships --filter "StudentController"
 *   node scripts/extract-graph.js --query files --filter "app/Http"
 *   node scripts/extract-graph.js --query imports --filter "Student"
 * 
 * Query types:
 *   - functions: Cari functions yang match pattern
 *   - classes: Cari classes yang match pattern
 *   - relationships: Cari incoming/outgoing edges dari specific node
 *   - files: Cari files yang match pattern
 *   - imports: Cari import relationships yang match pattern
 *   - structure: Lihat struktur direktori high-level
 */

import { existsSync, readFileSync, writeFileSync } from 'fs';
import { join } from 'path';

const graphPath = join(process.cwd(), '.understand-anything', 'knowledge-graph.json');

if (!existsSync(graphPath)) {
  console.error('❌ knowledge-graph.json tidak ditemukan di .understand-anything/');
  process.exit(1);
}

// Parse arguments
const args = process.argv.slice(2);
const getArgValue = (flag) => {
  const idx = args.indexOf(flag);
  return idx !== -1 ? args[idx + 1] : null;
};

const queryType = getArgValue('--query') || 'structure';
const filterPattern = getArgValue('--filter') || '';
const maxResults = parseInt(getArgValue('--limit') || '50');
const outputFormat = getArgValue('--format') || 'json'; // json atau text

try {
  console.log(`\n⏳ Loading graph and executing query: ${queryType}\n`);
  
  const graph = JSON.parse(readFileSync(graphPath, 'utf8'));
  let results = {};
  
  // Helper function: filter by pattern
  const filterByPattern = (items, pattern) => {
    if (!pattern) return items;
    const regex = new RegExp(pattern, 'i');
    return items.filter(item => 
      (item.id && regex.test(item.id)) || 
      (item.name && regex.test(item.name)) ||
      (item.label && regex.test(item.label)) ||
      (item.path && regex.test(item.path))
    );
  };
  
  // QUERY TYPE 1: FUNCTIONS
  if (queryType === 'functions') {
    const functions = (graph.nodes || []).filter(n => n.type === 'function');
    const filtered = filterByPattern(functions, filterPattern).slice(0, maxResults);
    
    results = {
      type: 'functions',
      count: filtered.length,
      items: filtered.map(fn => ({
        id: fn.id,
        name: fn.label || fn.id,
        file: fn.fileId,
        line: fn.line || 'unknown',
        // Count relationships
        relationshipCount: (graph.edges || []).filter(
          e => e.source === fn.id || e.target === fn.id
        ).length
      }))
    };
  }
  
  // QUERY TYPE 2: CLASSES
  else if (queryType === 'classes') {
    const classes = (graph.nodes || []).filter(n => n.type === 'class');
    const filtered = filterByPattern(classes, filterPattern).slice(0, maxResults);
    
    results = {
      type: 'classes',
      count: filtered.length,
      items: filtered.map(cls => ({
        id: cls.id,
        name: cls.label || cls.id,
        file: cls.fileId,
        namespace: cls.namespace || 'unknown',
        relationshipCount: (graph.edges || []).filter(
          e => e.source === cls.id || e.target === cls.id
        ).length
      }))
    };
  }
  
  // QUERY TYPE 3: FILES
  else if (queryType === 'files') {
    const files = (graph.nodes || []).filter(n => n.type === 'file');
    const filtered = filterByPattern(files, filterPattern).slice(0, maxResults);
    
    results = {
      type: 'files',
      count: filtered.length,
      items: filtered.map(file => {
        const nodeCount = (graph.nodes || []).filter(n => n.fileId === file.id).length;
        return {
          path: file.id,
          language: file.id.split('.').pop(),
          nodeCount: nodeCount,
          // List top items in this file
          topItems: (graph.nodes || [])
            .filter(n => n.fileId === file.id && (n.type === 'function' || n.type === 'class'))
            .slice(0, 5)
            .map(n => ({ name: n.label || n.id, type: n.type }))
        };
      })
    };
  }
  
  // QUERY TYPE 4: IMPORTS/DEPENDENCIES
  else if (queryType === 'imports') {
    const importEdges = (graph.edges || []).filter(
      e => e.type === 'imports' || e.type === 'import' || e.type === 'requires'
    );
    
    const filtered = importEdges.filter(edge => {
      if (!filterPattern) return true;
      const regex = new RegExp(filterPattern, 'i');
      return regex.test(edge.source) || regex.test(edge.target);
    }).slice(0, maxResults);
    
    results = {
      type: 'imports',
      count: filtered.length,
      items: filtered.map(edge => ({
        from: edge.source,
        to: edge.target,
        edgeType: edge.type
      }))
    };
  }
  
  // QUERY TYPE 5: RELATIONSHIPS (untuk specific node)
  else if (queryType === 'relationships') {
    const nodeId = filterPattern;
    
    if (!nodeId) {
      console.error('❌ --filter diperlukan untuk query relationships. Contoh:');
      console.error('   node scripts/extract-graph.js --query relationships --filter "StudentController"');
      process.exit(1);
    }
    
    const node = graph.nodes?.find(n => n.id === nodeId || n.label === nodeId);
    
    if (!node) {
      console.error(`❌ Node tidak ditemukan: ${nodeId}`);
      console.error('\n   Gunakan --query functions atau --query classes untuk list nodes dulu.\n');
      process.exit(1);
    }
    
    const incomingEdges = (graph.edges || []).filter(e => e.target === node.id);
    const outgoingEdges = (graph.edges || []).filter(e => e.source === node.id);
    
    results = {
      type: 'relationships',
      node: {
        id: node.id,
        label: node.label || node.id,
        type: node.type,
        file: node.fileId
      },
      incoming: {
        count: incomingEdges.length,
        edges: incomingEdges.slice(0, 10).map(e => ({
          from: e.source,
          type: e.type
        }))
      },
      outgoing: {
        count: outgoingEdges.length,
        edges: outgoingEdges.slice(0, 10).map(e => ({
          to: e.target,
          type: e.type
        }))
      }
    };
  }
  
  // QUERY TYPE 6: STRUCTURE (default)
  else if (queryType === 'structure') {
    const files = (graph.nodes || []).filter(n => n.type === 'file');
    const structure = {};
    
    files.forEach(file => {
      const parts = file.id.split('/');
      const topDir = parts[0];
      
      if (!structure[topDir]) {
        structure[topDir] = {
          files: [],
          fileCount: 0,
          nodeCount: 0
        };
      }
      
      structure[topDir].files.push(file.id);
      structure[topDir].fileCount += 1;
      structure[topDir].nodeCount += (graph.nodes || []).filter(n => n.fileId === file.id).length;
    });
    
    results = {
      type: 'structure',
      directories: Object.keys(structure).length,
      items: Object.entries(structure)
        .sort((a, b) => b[1].fileCount - a[1].fileCount)
        .slice(0, maxResults)
        .map(([dir, info]) => ({
          directory: dir,
          fileCount: info.fileCount,
          nodeCount: info.nodeCount,
          files: info.files.slice(0, 5) // Show first 5 files
        }))
    };
  }
  
  // OUTPUT
  if (outputFormat === 'json') {
    const outputPath = join(
      process.cwd(),
      '.understand-anything',
      `extract-${queryType}-${Date.now()}.json`
    );
    
    writeFileSync(outputPath, JSON.stringify(results, null, 2));
    
    // Print to console
    console.log(JSON.stringify(results, null, 2));
    console.log(`\n✅ Saved to: ${outputPath}`);
    
  } else if (outputFormat === 'text') {
    // Text output
    console.log(`\n📋 QUERY RESULTS: ${queryType}`);
    console.log(`   Items found: ${results.count || results.items?.length || 0}\n`);
    
    if (results.items) {
      results.items.forEach((item, i) => {
        console.log(`   ${i + 1}. ${JSON.stringify(item)}`);
      });
    } else {
      console.log(JSON.stringify(results, null, 2));
    }
  }
  
  console.log('');
  
} catch (error) {
  console.error('❌ ERROR:', error.message);
  process.exit(1);
}
