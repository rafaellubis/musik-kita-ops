#!/usr/bin/env node

/**
 * create-domain-references.js
 * ES Module version untuk Musik KITA
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const domainGraphPath = path.join(path.dirname(__dirname), 'domain-graph.json');
const kgGraphPath = path.join(path.dirname(__dirname), '.understand-anything', 'knowledge-graph.json');

if (!fs.existsSync(domainGraphPath) || !fs.existsSync(kgGraphPath)) {
  console.error('❌ ERROR: Missing required files');
  console.error(`   domain-graph.json: ${fs.existsSync(domainGraphPath) ? '✓' : '✗'}`);
  console.error(`   knowledge-graph.json: ${fs.existsSync(kgGraphPath) ? '✓' : '✗'}`);
  process.exit(1);
}

try {
  console.log('\n⏳ Loading both graphs and generating domain-aware references...\n');
  
  const domainGraph = JSON.parse(fs.readFileSync(domainGraphPath, 'utf8'));
  const kgGraph = JSON.parse(fs.readFileSync(kgGraphPath, 'utf8'));
  
  const understandAnythingDir = path.join(path.dirname(__dirname), '.understand-anything');
  
  // ========================================
  // REFERENCE 1: DOMAIN INDEX
  // ========================================
  
  const domainIndex = {
    generatedAt: new Date().toISOString(),
    project: domainGraph.project.name,
    totalDomains: 0,
    totalFlows: 0,
    totalSteps: 0,
    domains: []
  };
  
  const domainNodes = domainGraph.nodes.filter(n => n.type === 'domain');
  const flowNodes = domainGraph.nodes.filter(n => n.type === 'flow');
  const stepNodes = domainGraph.nodes.filter(n => n.type === 'step');
  
  domainIndex.totalDomains = domainNodes.length;
  domainIndex.totalFlows = flowNodes.length;
  domainIndex.totalSteps = stepNodes.length;
  
  domainNodes.forEach(domain => {
    const domainEntry = {
      id: domain.id,
      name: domain.name,
      summary: domain.summary,
      complexity: domain.complexity,
      businessRules: domain.domainMeta?.businessRules || [],
      crossDomainInteractions: domain.domainMeta?.crossDomainInteractions || [],
      flows: []
    };
    
    const flowEdges = domainGraph.edges.filter(e => 
      e.source === domain.id && e.type === 'domain_child'
    );
    
    flowEdges.forEach(edge => {
      const flow = flowNodes.find(f => f.id === edge.target);
      if (flow) {
        const flowEntry = {
          id: flow.id,
          name: flow.name,
          summary: flow.summary,
          complexity: flow.complexity,
          entryPoint: flow.domainMeta?.entryPoint || 'N/A',
          entryType: flow.domainMeta?.entryType || 'N/A',
          steps: []
        };
        
        const stepEdges = domainGraph.edges.filter(e =>
          e.source === flow.id && e.type === 'flow_step'
        );
        
        stepEdges.forEach(stepEdge => {
          const step = stepNodes.find(s => s.id === stepEdge.target);
          if (step) {
            flowEntry.steps.push({
              id: step.id,
              name: step.name,
              summary: step.summary,
              complexity: step.complexity,
              filePath: step.filePath || 'N/A',
              weight: stepEdge.weight || 0.5
            });
          }
        });
        
        domainEntry.flows.push(flowEntry);
      }
    });
    
    domainIndex.domains.push(domainEntry);
  });
  
  const domainIndexPath = path.join(understandAnythingDir, 'reference-domain-index.json');
  fs.writeFileSync(domainIndexPath, JSON.stringify(domainIndex, null, 2));
  
  console.log('✅ Created: reference-domain-index.json');
  console.log(`   - Domains: ${domainIndex.totalDomains}`);
  console.log(`   - Flows: ${domainIndex.totalFlows}`);
  console.log(`   - Steps: ${domainIndex.totalSteps}`);
  
  // ========================================
  // REFERENCE 2: DOMAIN TO CODE MAPPER
  // ========================================
  
  const domainToCodeMapper = {
    generatedAt: new Date().toISOString(),
    entities: {}
  };
  
  const entityNodes = domainGraph.nodes.filter(n => n.type === 'entity');
  
  entityNodes.forEach(entity => {
    const entityEntry = {
      domainId: entity.domainId || 'unknown',
      name: entity.name,
      description: entity.description || '',
      files: [],
      relatedFlows: [],
      relatedSteps: []
    };
    
    flowNodes.forEach(flow => {
      if (flow.domainMeta?.entities?.includes(entity.name)) {
        entityEntry.relatedFlows.push({
          name: flow.name,
          id: flow.id,
          entryPoint: flow.domainMeta?.entryPoint
        });
      }
    });
    
    stepNodes.forEach(step => {
      if (step.summary?.includes(entity.name)) {
        entityEntry.relatedSteps.push({
          name: step.name,
          file: step.filePath,
          summary: step.summary.substring(0, 100)
        });
      }
    });
    
    const kgFiles = (kgGraph.nodes || []).filter(n => n.type === 'file');
    kgFiles.forEach(file => {
      if (file.id.includes(entity.name.toLowerCase()) || 
          file.id.includes(entity.name.split(/(?=[A-Z])/).join('').toLowerCase())) {
        entityEntry.files.push({
          path: file.id,
          type: file.id.split('.').pop()
        });
      }
    });
    
    domainToCodeMapper.entities[entity.name] = entityEntry;
  });
  
  const mapperPath = path.join(understandAnythingDir, 'reference-domain-to-code-mapper.json');
  fs.writeFileSync(mapperPath, JSON.stringify(domainToCodeMapper, null, 2));
  
  console.log('✅ Created: reference-domain-to-code-mapper.json');
  console.log(`   - Entities mapped: ${Object.keys(domainToCodeMapper.entities).length}`);
  
  // ========================================
  // REFERENCE 3: CROSS-DOMAIN IMPACT
  // ========================================
  
  const crossDomainImpact = {
    generatedAt: new Date().toISOString(),
    scenarios: []
  };
  
  domainNodes.forEach(domain => {
    if (domain.domainMeta?.crossDomainInteractions) {
      const scenario = {
        domain: domain.name,
        domainId: domain.id,
        businessRules: domain.domainMeta.businessRules || [],
        impacts: domain.domainMeta.crossDomainInteractions.map(interaction => ({
          description: interaction,
          affectedDomains: [],
          affectedFlows: []
        }))
      };
      
      crossDomainImpact.scenarios.push(scenario);
    }
  });
  
  const impactPath = path.join(understandAnythingDir, 'reference-cross-domain-impact.json');
  fs.writeFileSync(impactPath, JSON.stringify(crossDomainImpact, null, 2));
  
  console.log('✅ Created: reference-cross-domain-impact.json');
  console.log(`   - Scenarios: ${crossDomainImpact.scenarios.length}`);
  
  // ========================================
  // REFERENCE 4: DOMAIN GUIDE
  // ========================================
  
  const guideContent = `# Musik KITA Domain-Aware Reference Guide

Generated: ${new Date().toISOString()}

## Overview

These references combine **Domain Graph** (business logic) + **Knowledge Graph** (code structure).

**Files:**
1. reference-domain-index.json - Full domain/flow/step structure with code locations
2. reference-domain-to-code-mapper.json - Entity → Code file mapping
3. reference-cross-domain-impact.json - What breaks when you change X?

## Workflow Examples

### Find domain and flows
\`\`\`bash
node scripts/query-domain-code.js --domain "student-lifecycle" --list-steps
\`\`\`

### Find entity and impact
\`\`\`bash
node scripts/query-domain-code.js --entity "Student" --show-impact
\`\`\`

### Search across domains
\`\`\`bash
node scripts/query-domain-code.js --search "honor"
\`\`\`

## For Cursor Integration

Create \`.cursor/rules/musik-kita-domain.mdc\` with:

\`\`\`markdown
# Musik KITA Domain-Aware Development

When answering questions:
1. Check reference-domain-index.json first
2. Use exact filePath from steps
3. Reference reference-cross-domain-impact.json for side effects

{include .understand-anything/reference-domain-index.json}
\`\`\`

See REFERENCE-DOMAIN-GUIDE.md for complete documentation.
`;
  
  const guidePath = path.join(understandAnythingDir, 'REFERENCE-DOMAIN-GUIDE.md');
  fs.writeFileSync(guidePath, guideContent);
  
  console.log('✅ Created: REFERENCE-DOMAIN-GUIDE.md');
  
  console.log('\n' + '='.repeat(70));
  console.log('\n✅ ALL DOMAIN-AWARE REFERENCES GENERATED\n');
  console.log('📁 Files created in .understand-anything/:\n');
  console.log('   1. reference-domain-index.json');
  console.log('   2. reference-domain-to-code-mapper.json');
  console.log('   3. reference-cross-domain-impact.json');
  console.log('   4. REFERENCE-DOMAIN-GUIDE.md\n');
  console.log('⏭️  Next: node scripts/query-domain-code.js --domain "student"\n');
  
} catch (error) {
  console.error('❌ ERROR:', error.message);
  console.error(error.stack);
  process.exit(1);
}
