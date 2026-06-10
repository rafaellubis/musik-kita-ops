#!/usr/bin/env node

/**
 * query-domain-code.js
 * ES Module version untuk Musik KITA
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const domainGraphPath = path.join(path.dirname(__dirname), 'domain-graph.json');
const domainIndexPath = path.join(path.dirname(__dirname), '.understand-anything', 'reference-domain-index.json');

const missingFiles = [];
if (!fs.existsSync(domainGraphPath)) missingFiles.push('domain-graph.json');
if (!fs.existsSync(domainIndexPath)) missingFiles.push('reference-domain-index.json');

if (missingFiles.length > 0) {
  console.error(`\n❌ Missing required files: ${missingFiles.join(', ')}`);
  console.error(`\n   Run: node scripts/create-domain-references.js\n`);
  process.exit(1);
}

const args = process.argv.slice(2);
const getArgValue = (flag) => {
  const idx = args.indexOf(flag);
  return idx !== -1 ? args[idx + 1] : null;
};

const queryType = args[0]?.startsWith('--') ? args[0].replace('--', '') : null;
const queryValue = getArgValue('--' + queryType);
const showImpact = args.includes('--show-impact');
const listSteps = args.includes('--list-steps');

try {
  const domainIndex = JSON.parse(fs.readFileSync(domainIndexPath, 'utf8'));
  const domainGraph = JSON.parse(fs.readFileSync(domainGraphPath, 'utf8'));
  
  let result = {
    query: { type: queryType, value: queryValue },
    timestamp: new Date().toISOString(),
    results: null
  };
  
  // QUERY TYPE 1: DOMAIN
  if (queryType === 'domain') {
    const searchTerm = queryValue?.toLowerCase() || '';
    const domain = domainIndex.domains.find(d => 
      d.id.toLowerCase().includes(searchTerm) || 
      d.name.toLowerCase().includes(searchTerm)
    );
    
    if (!domain) {
      console.error(`\n❌ Domain tidak ditemukan: "${queryValue}"\n`);
      console.log('Available domains:');
      domainIndex.domains.forEach(d => {
        console.log(`   - ${d.name} (${d.flows.length} flows)`);
      });
      console.log('');
      process.exit(1);
    }
    
    result.results = {
      type: 'domain',
      domain: {
        id: domain.id,
        name: domain.name,
        summary: domain.summary,
        complexity: domain.complexity,
        businessRules: domain.businessRules,
        crossDomainInteractions: domain.crossDomainInteractions,
        flowCount: domain.flows.length,
        flows: domain.flows.map(f => ({
          name: f.name,
          entryPoint: f.entryPoint,
          stepCount: f.steps.length,
          ...(listSteps && { steps: f.steps })
        }))
      }
    };
  }
  
  // QUERY TYPE 2: ENTITY
  else if (queryType === 'entity') {
    const mapperPath = path.join(path.dirname(__dirname), '.understand-anything', 'reference-domain-to-code-mapper.json');
    if (!fs.existsSync(mapperPath)) {
      console.error('\n❌ reference-domain-to-code-mapper.json tidak ditemukan');
      console.error('   Run: node scripts/create-domain-references.js\n');
      process.exit(1);
    }
    
    const mapper = JSON.parse(fs.readFileSync(mapperPath, 'utf8'));
    const entity = mapper.entities[queryValue];
    
    if (!entity) {
      console.error(`\n❌ Entity tidak ditemukan: "${queryValue}"\n`);
      console.log('Available entities:');
      Object.keys(mapper.entities).forEach(e => {
        console.log(`   - ${e}`);
      });
      console.log('');
      process.exit(1);
    }
    
    result.results = {
      type: 'entity',
      entity: queryValue,
      summary: entity.description,
      files: entity.files,
      relatedFlows: entity.relatedFlows,
      relatedSteps: entity.relatedSteps.slice(0, 5)
    };
    
    if (showImpact) {
      const impactPath = path.join(path.dirname(__dirname), '.understand-anything', 'reference-cross-domain-impact.json');
      if (fs.existsSync(impactPath)) {
        const impact = JSON.parse(fs.readFileSync(impactPath, 'utf8'));
        result.results.impacts = impact.scenarios.filter(s =>
          s.businessRules?.some(r => r.includes(queryValue)) ||
          s.impacts?.some(i => i.description.includes(queryValue))
        );
      }
    }
  }
  
  // QUERY TYPE 3: FLOW
  else if (queryType === 'flow') {
    const searchTerm = queryValue?.toLowerCase() || '';
    let foundFlow = null;
    let foundDomain = null;
    
    for (const domain of domainIndex.domains) {
      const flow = domain.flows.find(f =>
        f.name.toLowerCase().includes(searchTerm) ||
        f.id.toLowerCase().includes(searchTerm)
      );
      if (flow) {
        foundFlow = flow;
        foundDomain = domain;
        break;
      }
    }
    
    if (!foundFlow) {
      console.error(`\n❌ Flow tidak ditemukan: "${queryValue}"\n`);
      process.exit(1);
    }
    
    result.results = {
      type: 'flow',
      domain: foundDomain.name,
      flow: {
        id: foundFlow.id,
        name: foundFlow.name,
        summary: foundFlow.summary,
        complexity: foundFlow.complexity,
        entryPoint: foundFlow.entryPoint,
        entryType: foundFlow.entryType,
        stepCount: foundFlow.steps.length,
        steps: foundFlow.steps.map(s => ({
          order: foundFlow.steps.indexOf(s) + 1,
          name: s.name,
          summary: s.summary,
          filePath: s.filePath,
          complexity: s.complexity
        }))
      }
    };
  }
  
  // QUERY TYPE 4: SEARCH
  else if (queryType === 'search') {
    const term = queryValue?.toLowerCase() || '';
    
    const results = {
      domains: [],
      flows: [],
      steps: [],
      businessRules: []
    };
    
    domainIndex.domains.forEach(domain => {
      if (domain.name.toLowerCase().includes(term) || 
          domain.summary.toLowerCase().includes(term)) {
        results.domains.push({
          name: domain.name,
          summary: domain.summary
        });
      }
      
      domain.flows.forEach(flow => {
        if (flow.name.toLowerCase().includes(term) ||
            flow.summary.toLowerCase().includes(term)) {
          results.flows.push({
            domain: domain.name,
            name: flow.name,
            summary: flow.summary,
            entryPoint: flow.entryPoint
          });
        }
        
        flow.steps.forEach(step => {
          if (step.name.toLowerCase().includes(term) ||
              step.summary.toLowerCase().includes(term)) {
            results.steps.push({
              domain: domain.name,
              flow: flow.name,
              name: step.name,
              summary: step.summary,
              filePath: step.filePath
            });
          }
        });
      });
      
      (domain.businessRules || []).forEach(rule => {
        if (rule.toLowerCase().includes(term)) {
          results.businessRules.push({
            domain: domain.name,
            rule: rule
          });
        }
      });
    });
    
    result.results = {
      type: 'search',
      searchTerm: queryValue,
      matchCount: {
        domains: results.domains.length,
        flows: results.flows.length,
        steps: results.steps.length,
        businessRules: results.businessRules.length,
        total: results.domains.length + results.flows.length + 
               results.steps.length + results.businessRules.length
      },
      matches: results
    };
  }
  
  // QUERY TYPE 5: BUSINESS RULE
  else if (queryType === 'business-rule') {
    const term = queryValue?.toLowerCase() || '';
    const rules = [];
    
    domainIndex.domains.forEach(domain => {
      (domain.businessRules || []).forEach(rule => {
        if (rule.toLowerCase().includes(term)) {
          rules.push({
            domain: domain.name,
            rule: rule,
            domainId: domain.id,
            relatedFlows: domain.flows.map(f => f.name)
          });
        }
      });
    });
    
    result.results = {
      type: 'business-rule',
      searchTerm: queryValue,
      count: rules.length,
      rules: rules
    };
  }
  
  // QUERY TYPE 6: IMPACT
  else if (queryType === 'impact') {
    const impactPath = path.join(path.dirname(__dirname), '.understand-anything', 'reference-cross-domain-impact.json');
    if (!fs.existsSync(impactPath)) {
      console.error('\n❌ reference-cross-domain-impact.json tidak ditemukan');
      process.exit(1);
    }
    
    const impact = JSON.parse(fs.readFileSync(impactPath, 'utf8'));
    const domain = domainIndex.domains.find(d =>
      d.name.toLowerCase().includes(queryValue?.toLowerCase() || '')
    );
    
    if (!domain) {
      console.error(`\n❌ Domain tidak ditemukan: "${queryValue}"\n`);
      process.exit(1);
    }
    
    const scenario = impact.scenarios.find(s => s.domain === domain.name);
    
    result.results = {
      type: 'impact',
      domain: domain.name,
      businessRules: domain.businessRules,
      crossDomainInteractions: domain.crossDomainInteractions,
      impacts: scenario?.impacts || [],
      affectedDomains: new Set(
        (scenario?.impacts || []).flatMap(i => i.affectedDomains)
      ).size
    };
  }
  
  // HELP
  else if (!queryType || queryType === 'help') {
    console.log(`
📚 QUERY DOMAIN-CODE TOOL

Usage:
  node scripts/query-domain-code.js --<type> "<value>" [options]

Query Types:

  --domain "student-lifecycle"
    Options: --list-steps

  --entity "Student"
    Options: --show-impact

  --flow "Trial Class"

  --search "honor calculation"

  --business-rule "honor"

  --impact "student-lifecycle"

Examples:

  node scripts/query-domain-code.js --domain "student" --list-steps
  node scripts/query-domain-code.js --entity "Student" --show-impact
  node scripts/query-domain-code.js --search "honor"

`);
    process.exit(0);
  }
  
  // OUTPUT
  const understandAnythingDir = path.join(path.dirname(__dirname), '.understand-anything');
  const outputPath = path.join(
    understandAnythingDir,
    `query-${queryType}-${Date.now()}.json`
  );
  fs.writeFileSync(outputPath, JSON.stringify(result, null, 2));
  
  console.log('\n' + JSON.stringify(result, null, 2) + '\n');
  console.log(`📄 Saved to: ${outputPath}`);
  
} catch (error) {
  console.error('\n❌ ERROR:', error.message);
  console.error(error.stack);
  process.exit(1);
}
