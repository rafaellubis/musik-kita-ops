#!/usr/bin/env node

/**
 * analyze-graph.js
 * 
 * Analisis struktur knowledge-graph.json dari Understand-Anything plugin
 * 
 * Cara pakai:
 *   node scripts/analyze-graph.js
 * 
 * Output:
 *   - .understand-anything/graph-stats.json (untuk reference nanti)
 *   - Console output dengan summary
 */

import { existsSync, readFileSync, statSync, writeFileSync } from 'fs';
import { join } from 'path';

const graphPath = join(process.cwd(), '.understand-anything', 'knowledge-graph.json');

// Cek apakah file ada
if (!existsSync(graphPath)) {
  console.error('❌ ERROR: knowledge-graph.json tidak ditemukan');
  console.error(`   Path yang dicari: ${graphPath}`);
  console.error('\n   Pastikan:');
  console.error('   1. File ada di .understand-anything/knowledge-graph.json');
  console.error('   2. Anda menjalankan script dari root folder musik-kita-ops');
  console.error('   3. Anda sudah run /understand command di Cursor/Claude Code sebelumnya\n');
  process.exit(1);
}

try {
  console.log('\n⏳ Loading knowledge-graph.json...\n');
  
  // Baca file
  const fileContent = readFileSync(graphPath, 'utf8');
  const graph = JSON.parse(fileContent);
  
  // Hitung statistik dasar
  const stats = {
    timestamp: new Date().toISOString(),
    graphPath: graphPath,
    fileSizeKB: Math.round(statSync(graphPath).size / 1024),
    
    totalNodes: graph.nodes?.length || 0,
    totalEdges: graph.edges?.length || 0,
    
    nodesByType: {},
    edgesByType: {},
    fileLanguages: {},
    
    // Untuk token calculation
    tokenEstimates: {},
    
    // Top items
    topFilesByComplexity: [],
    topFunctionsByRelationships: [],
    topImportedModules: []
  };
  
  // 1. Count nodes by type
  if (graph.nodes) {
    graph.nodes.forEach(node => {
      if (node.type) {
        stats.nodesByType[node.type] = (stats.nodesByType[node.type] || 0) + 1;
      }
    });
  }
  
  // 2. Count edges by type
  if (graph.edges) {
    graph.edges.forEach(edge => {
      if (edge.type) {
        stats.edgesByType[edge.type] = (stats.edgesByType[edge.type] || 0) + 1;
      }
    });
  }
  
  // 3. File languages
  const files = (graph.nodes || []).filter(n => n.type === 'file');
  files.forEach(file => {
    const ext = file.id?.split('.').pop() || 'unknown';
    stats.fileLanguages[ext] = (stats.fileLanguages[ext] || 0) + 1;
  });
  
  // 4. Top files by complexity (jumlah nodes per file)
  const fileComplexity = {};
  (graph.nodes || []).forEach(node => {
    if (node.fileId) {
      fileComplexity[node.fileId] = (fileComplexity[node.fileId] || 0) + 1;
    }
  });
  stats.topFilesByComplexity = Object.entries(fileComplexity)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 15)
    .map(([file, count]) => ({ file, nodeCount: count }));
  
  // 5. Top functions/classes by relationships
  const itemRelationships = {};
  (graph.nodes || []).forEach(node => {
    if (node.type === 'function' || node.type === 'class') {
      const count = (graph.edges || []).filter(
        e => e.source === node.id || e.target === node.id
      ).length;
      itemRelationships[node.id] = {
        name: node.label || node.id,
        type: node.type,
        relationshipCount: count,
        file: node.fileId
      };
    }
  });
  stats.topFunctionsByRelationships = Object.values(itemRelationships)
    .sort((a, b) => b.relationshipCount - a.relationshipCount)
    .slice(0, 10);
  
  // 6. Top imported modules
  const importCounts = {};
  (graph.edges || []).forEach(edge => {
    if (edge.type === 'imports' || edge.type === 'import') {
      importCounts[edge.target] = (importCounts[edge.target] || 0) + 1;
    }
  });
  stats.topImportedModules = Object.entries(importCounts)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 10)
    .map(([module, count]) => ({ module, importCount: count }));
  
  // 7. Token estimation
  const rawSourceEstimate = stats.fileSizeKB * 4; // Rough: 1KB ≈ 250 tokens, file JSON overhead
  const graphOnlyEstimate = (stats.fileSizeKB * 0.15) * 4; // Graph maybe 15% of raw
  const selectiveEstimate = (stats.fileSizeKB * 0.25) * 4; // With selective code snippets
  
  stats.tokenEstimates = {
    fullCodebaseTokens: rawSourceEstimate,
    graphOnlyTokens: graphOnlyEstimate,
    graphPlusSelectiveTokens: selectiveEstimate,
    estimatedSavings: Math.round((1 - selectiveEstimate / rawSourceEstimate) * 100) + '%'
  };
  
  // PRINT SUMMARY
  console.log('╔' + '═'.repeat(68) + '╗');
  console.log('║' + ' MUSIK KITA KNOWLEDGE GRAPH ANALYSIS '.padEnd(68) + '║');
  console.log('╚' + '═'.repeat(68) + '╝');
  
  console.log(`\n📊 BASIC STATS`);
  console.log(`   File size: ${stats.fileSizeKB} KB`);
  console.log(`   Total nodes: ${stats.totalNodes}`);
  console.log(`   Total edges: ${stats.totalEdges}`);
  
  console.log(`\n📁 FILE BREAKDOWN (by language)`);
  Object.entries(stats.fileLanguages)
    .sort((a, b) => b[1] - a[1])
    .forEach(([lang, count]) => {
      console.log(`   .${lang.padEnd(10)} ${count} files`);
    });
  
  console.log(`\n🔧 NODE BREAKDOWN (by type)`);
  Object.entries(stats.nodesByType)
    .sort((a, b) => b[1] - a[1])
    .forEach(([type, count]) => {
      console.log(`   ${type.padEnd(15)} ${count}`);
    });
  
  console.log(`\n🔗 RELATIONSHIP BREAKDOWN (by type)`);
  Object.entries(stats.edgesByType)
    .sort((a, b) => b[1] - a[1])
    .forEach(([type, count]) => {
      console.log(`   ${type.padEnd(15)} ${count}`);
    });
  
  console.log(`\n🔥 TOP 10 MOST COMPLEX FILES`);
  stats.topFilesByComplexity.slice(0, 10).forEach(({ file, nodeCount }, i) => {
    console.log(`   ${(i + 1).toString().padEnd(2)}. ${file.padEnd(50)} (${nodeCount} nodes)`);
  });
  
  console.log(`\n🎯 TOP 5 MOST CONNECTED ITEMS`);
  stats.topFunctionsByRelationships.slice(0, 5).forEach(({ name, type, relationshipCount }, i) => {
    console.log(`   ${(i + 1)}. ${name.padEnd(40)} [${type}] (${relationshipCount} rels)`);
  });
  
  console.log(`\n⚡ TOKEN ESTIMATION`);
  console.log(`   Full codebase: ~${Math.round(stats.tokenEstimates.fullCodebaseTokens)} tokens`);
  console.log(`   Graph only: ~${Math.round(stats.tokenEstimates.graphOnlyTokens)} tokens`);
  console.log(`   Graph + selective code: ~${Math.round(stats.tokenEstimates.graphPlusSelectiveTokens)} tokens`);
  console.log(`   \n   💰 Estimated savings: ${stats.tokenEstimates.estimatedSavings}`);
  
  // Save stats file
  const statsFile = join(process.cwd(), '.understand-anything', 'graph-stats.json');
  writeFileSync(statsFile, JSON.stringify(stats, null, 2));
  
  console.log(`\n✅ DONE`);
  console.log(`   Stats saved to: .understand-anything/graph-stats.json`);
  console.log(`   Next step: node scripts/create-reference.js\n`);
  
} catch (error) {
  console.error('❌ ERROR:', error.message);
  process.exit(1);
}
