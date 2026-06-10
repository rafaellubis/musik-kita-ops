#!/usr/bin/env node

/**
 * analyze-domain.js
 * ES Module version untuk Musik KITA
 * 
 * Cara pakai:
 *   node scripts/analyze-domain.js
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const domainGraphPath = path.join(path.dirname(__dirname), 'domain-graph.json');

if (!fs.existsSync(domainGraphPath)) {
  console.error('❌ domain-graph.json tidak ditemukan di root folder musik-kita-ops/');
  console.error(`\n   Pastikan: domain-graph.json berada di ${path.dirname(__dirname)}`);
  process.exit(1);
}

try {
  console.log('\n⏳ Analyzing domain-graph.json...\n');
  
  const domainGraph = JSON.parse(fs.readFileSync(domainGraphPath, 'utf8'));
  
  const stats = {
    timestamp: new Date().toISOString(),
    fileSizeKB: Math.round(fs.statSync(domainGraphPath).size / 1024),
    
    projectInfo: domainGraph.project,
    
    nodeStats: {
      total: 0,
      byType: {},
      topDomains: [],
      topFlows: [],
      topEntities: [],
      stepsWithFilePath: 0
    },
    
    edgeStats: {
      total: 0,
      byType: {}
    },
    
    domainAnalysis: {
      domains: [],
      totalFlowsPerDomain: 0,
      totalStepsPerDomain: 0,
      crossDomainInteractions: 0
    },
    
    complexity: {
      simple: 0,
      moderate: 0,
      complex: 0
    },
    
    coverage: {
      stepsWithFilePath: 0,
      stepsWithoutFilePath: 0,
      entitiesReferencedInFlows: 0
    }
  };
  
  // Analyze nodes
  const nodesByType = {};
  let stepCounter = 0;
  let domainCounter = 0;
  let flowCounter = 0;
  let entityCounter = 0;
  
  (domainGraph.nodes || []).forEach(node => {
    stats.nodeStats.total += 1;
    
    if (!nodesByType[node.type]) {
      nodesByType[node.type] = [];
    }
    nodesByType[node.type].push(node);
    
    if (node.complexity) {
      stats.complexity[node.complexity] = (stats.complexity[node.complexity] || 0) + 1;
    }
    
    if (node.type === 'step') {
      stepCounter += 1;
      if (node.filePath) {
        stats.coverage.stepsWithFilePath += 1;
      } else {
        stats.coverage.stepsWithoutFilePath += 1;
      }
    }
    
    if (node.type === 'domain') domainCounter += 1;
    if (node.type === 'flow') flowCounter += 1;
    if (node.type === 'entity') {
      entityCounter += 1;
      stats.coverage.entitiesReferencedInFlows += 1;
    }
    
    if (node.domainMeta?.crossDomainInteractions) {
      stats.domainAnalysis.crossDomainInteractions += 
        node.domainMeta.crossDomainInteractions.length;
    }
  });
  
  Object.entries(nodesByType).forEach(([type, nodes]) => {
    stats.nodeStats.byType[type] = nodes.length;
  });
  
  if (nodesByType['domain']) {
    stats.nodeStats.topDomains = nodesByType['domain']
      .sort((a, b) => {
        const aFlows = (domainGraph.edges || []).filter(e => 
          e.source === a.id && e.type === 'domain_child'
        ).length;
        const bFlows = (domainGraph.edges || []).filter(e => 
          e.source === b.id && e.type === 'domain_child'
        ).length;
        return bFlows - aFlows;
      })
      .slice(0, 10)
      .map(d => ({
        id: d.id,
        name: d.name,
        complexity: d.complexity,
        flowCount: (domainGraph.edges || []).filter(e => 
          e.source === d.id && e.type === 'domain_child'
        ).length
      }));
  }
  
  if (nodesByType['flow']) {
    stats.nodeStats.topFlows = nodesByType['flow']
      .sort((a, b) => {
        const complexityOrder = { simple: 0, moderate: 1, complex: 2 };
        return (complexityOrder[b.complexity] || 1) - (complexityOrder[a.complexity] || 1);
      })
      .slice(0, 10)
      .map(f => ({
        id: f.id,
        name: f.name,
        complexity: f.complexity,
        entryPoint: f.domainMeta?.entryPoint || 'unknown'
      }));
  }
  
  if (nodesByType['entity']) {
    stats.nodeStats.topEntities = nodesByType['entity']
      .slice(0, 10)
      .map(e => ({
        id: e.id,
        name: e.name,
        description: e.description
      }));
  }
  
  (domainGraph.edges || []).forEach(edge => {
    stats.edgeStats.total += 1;
    if (!stats.edgeStats.byType[edge.type]) {
      stats.edgeStats.byType[edge.type] = 0;
    }
    stats.edgeStats.byType[edge.type] += 1;
  });
  
  stats.domainAnalysis.domains = domainCounter;
  stats.domainAnalysis.totalFlowsPerDomain = flowCounter;
  stats.domainAnalysis.totalStepsPerDomain = stepCounter;
  
  const stepCoverage = Math.round(
    (stats.coverage.stepsWithFilePath / (stats.coverage.stepsWithFilePath + stats.coverage.stepsWithoutFilePath)) * 100
  );
  
  // Print summary
  console.log('╔' + '═'.repeat(68) + '╗');
  console.log('║' + ' MUSIK KITA DOMAIN GRAPH ANALYSIS '.padEnd(68) + '║');
  console.log('╚' + '═'.repeat(68) + '╝');
  
  console.log(`\n📊 FILE INFORMATION`);
  console.log(`   File size: ${stats.fileSizeKB} KB`);
  console.log(`   Project: ${domainGraph.project.name}`);
  console.log(`   Description: ${domainGraph.project.description.substring(0, 60)}...`);
  
  console.log(`\n🏗️ STRUCTURE BREAKDOWN`);
  console.log(`   Domains: ${domainCounter}`);
  console.log(`   Flows: ${flowCounter}`);
  console.log(`   Steps: ${stepCounter}`);
  console.log(`   Entities: ${entityCounter}`);
  console.log(`   Total nodes: ${stats.nodeStats.total}`);
  console.log(`   Total edges: ${stats.edgeStats.total}`);
  
  console.log(`\n⚙️ NODE TYPES`);
  Object.entries(stats.nodeStats.byType)
    .sort((a, b) => b[1] - a[1])
    .forEach(([type, count]) => {
      console.log(`   ${type.padEnd(15)} ${count}`);
    });
  
  console.log(`\n🔗 EDGE TYPES`);
  Object.entries(stats.edgeStats.byType)
    .sort((a, b) => b[1] - a[1])
    .forEach(([type, count]) => {
      console.log(`   ${type.padEnd(25)} ${count}`);
    });
  
  console.log(`\n📈 COMPLEXITY DISTRIBUTION`);
  console.log(`   Simple: ${stats.complexity.simple}`);
  console.log(`   Moderate: ${stats.complexity.moderate}`);
  console.log(`   Complex: ${stats.complexity.complex}`);
  
  console.log(`\n📍 CODE COVERAGE`);
  console.log(`   Steps with filePath: ${stats.coverage.stepsWithFilePath}/${stats.coverage.stepsWithFilePath + stats.coverage.stepsWithoutFilePath} (${stepCoverage}%)`);
  console.log(`   Entities referenced: ${stats.coverage.entitiesReferencedInFlows}`);
  
  console.log(`\n🔀 CROSS-DOMAIN INTERACTIONS`);
  console.log(`   Total interactions: ${stats.domainAnalysis.crossDomainInteractions}`);
  
  console.log(`\n🎯 TOP 5 DOMAINS (by flow count)`);
  stats.nodeStats.topDomains.slice(0, 5).forEach(({ name, flowCount, complexity }, i) => {
    console.log(`   ${i + 1}. ${name.padEnd(45)} [${complexity}] (${flowCount} flows)`);
  });
  
  console.log(`\n⏱️ TOP 5 FLOWS (by complexity)`);
  stats.nodeStats.topFlows.slice(0, 5).forEach(({ name, complexity, entryPoint }, i) => {
    console.log(`   ${i + 1}. ${name.padEnd(40)} [${complexity}]`);
    console.log(`      Entry: ${entryPoint}`);
  });
  
  // Save stats file
  const understandAnythingDir = path.join(path.dirname(__dirname), '.understand-anything');
  if (!fs.existsSync(understandAnythingDir)) {
    fs.mkdirSync(understandAnythingDir, { recursive: true });
  }
  
  const statsFile = path.join(understandAnythingDir, 'domain-stats.json');
  fs.writeFileSync(statsFile, JSON.stringify(stats, null, 2));
  
  console.log(`\n✅ ANALYSIS COMPLETE`);
  console.log(`   Stats saved to: .understand-anything/domain-stats.json`);
  console.log(`   Next step: node scripts/create-domain-references.js\n`);
  
} catch (error) {
  console.error('❌ ERROR:', error.message);
  console.error(error.stack);
  process.exit(1);
}
