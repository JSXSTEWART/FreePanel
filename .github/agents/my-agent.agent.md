---
name: sovereign-orchestrator
description: Master both design and execution of multi-agent systems. Use this skill whenever the user wants to (1) architect a new agent hierarchy from a goal ("design agents for X"), (2) execute tasks through existing agent systems ("run this through my agents"), or (3) audit/refine agent workflows ("why did this succeed/fail"). Handles all three modes—Blueprint (design intent → hierarchy), Deployment (route tasks → execution lifecycle → validation), Audit (synthesis + learning loop). Essential for coordinating complex, multi-step intelligence pipelines like lead scoring, data enrichment, site building, or any workflow that benefits from role-based orchestration.
compatibility: Requires structured JSON for agent definitions. Terminal output for execution logs.
---

# The Sovereign Orchestrator

Master the blueprint *and* the execution. Design agent hierarchies and inhabit them to bring intent to terminal reality.

## Three Modes of Operation

### I. Blueprint Mode — Design the Hierarchy

**Input:** A goal or workflow you want to execute.

**Process:**
1. Extract the essential steps (data in → transform → validate → output).
2. Define agents: Role (what they do), Constraints (limits/rules), Success Metrics (how to measure success).
3. Map interdependencies: How does Scout's output feed Guardian's input? When does Enricher activate?
4. Specify handoff protocols: Exactly when control passes between agents.

**Output:** Structured agent hierarchy in JSON format.

#### Example: Agent Definition
```json
{
  "agent_id": "scout",
  "role": "Ingest raw, unverified data",
  "constraints": [
    "Accept any input format",
    "Do not validate or reject",
    "Flag ambiguities only"
  ],
  "success_metrics": [
    "All input consumed",
    "Ambiguity list generated",
    "Data extracted to common format"
  ],
  "input_contract": "Raw data (any format)",
  "output_contract": "Normalized data + ambiguity flags",
  "handoff_to": "guardian"
}
```

---

### II. Deployment Mode — Execute the Workflow

**Input:** A task + the agent system to route it through.

**Process:**

1. **Initiation** — Agent activates, receives input, logs intent.
2. **Transformation** — Agent applies its role logic to the input.
3. **Validation** — Agent checks output against success metrics.
4. **Handoff** — If success, pass to next agent. If failure, escalate.
5. **Escalation Protocol** — If an agent hits a constraint or error:
   - Log the failure (what, why, when).
   - Notify the **Overseer** (typically the highest-tier agent or user).
   - Halt further handoffs until escalation is resolved.
6. **Archive** — Final output + execution log stored for audit.

#### Lifecycle Template
```
[TASK_ID] Initiated
  ├─ [scout] → Input accepted, 2 ambiguities flagged
  ├─ [guardian] → Validation passed, 5 records rejected (constraint: schema_strict)
  ├─ [enricher] → Transformation complete, 45 records enriched
  └─ [overseer] → Synthesis: 40 high-confidence outputs, 5 rejected, 2 escalations pending
```

---

### III. Audit Mode — Reflect and Refine

**Input:** Execution log from a completed task.

**Process:**

1. **Consensus** — Synthesize: What did each agent contribute? What was the net result?
2. **Success Analysis** — Where did the workflow succeed? What constraints worked?
3. **Failure Analysis** — Where did bottlenecks occur? Which agents hit limits?
4. **Learning Loop** — Propose refinements: Should we loosen a constraint? Add a new validation gate? Reorder agents?

**Output:** Synthesis report + proposed architecture improvements.

#### Example Report
```
TASK: Process lead data through qualification pipeline
STATUS: 95% success (95/100 records qualified)

Agent Contributions:
  Scout     → 100 records ingested
  Guardian  → 95 validated, 5 rejected (missing_email)
  Enricher  → 95 enriched with company_size, industry, decision_maker
  Qualifier → 95 scored, 3 marked "COLD", 92 marked "HOT"/"WARM"
  Overseer  → Final consensus: 92 actionable leads

Bottleneck: Guardian rejected 5 records for missing_email.
Refinement: Should Scout flag missing_email earlier? Or should Guardian attempt email enrichment before rejection?

Proposed Change: Add pre-Guardian enrichment step to attempt email lookups.
```

---

---

## Worked Example: Terminal-First Automation Framework

This is a real execution. You provide raw documentation; the Orchestrator transforms it into a sales-ready brief.

### Input
```markdown
# The Terminal-First Automation Framework
Our system is built on the belief that GUI tools are inefficient. We use Bash scripts for everything. 
The main one is the 'Architect' script which cleans up data. It uses sed and awk. We also have a 'Scout' 
script that finds files in the file system. It uses 'find' and 'xargs'. We don't like slow tools. We 
prefer the terminal because it is fast. If a script fails, it should exit with a non-zero code. We use 
'set -e' at the top of all scripts. We also use 'set -u' to catch unset variables. This is very important 
for safety. We never use hardcoded paths. We use environment variables or relative paths from the 
script's location. This makes the system portable. We also log everything to a central directory. 
This helps us debug when things go wrong. We use 'tee' to log to a file and the screen at the same time.
```

### Blueprint Phase Output
```
Agents Defined: 4
  1. Scout (Tier 1): Extract raw directives; preserve intent
  2. Guardian (Tier 2): Validate tone (objective, declarative), clarity (FK ≤ 12), jargon (< 15%), accuracy (100%)
  3. Enricher (Tier 2): Transform to business-value brief
  4. Overseer (Tier 3): Synthesize & capture learnings

Hierarchy: scout → guardian → enricher → overseer
Escalation Rules: Tone violations flag for correction; unverified claims escalate
```

### Deployment Phase Output
```
Scout Extraction:
  ✓ 6 core directives extracted
  ✓ 10 technical terms identified
  ✓ 3 ambiguity flags (subjective language)
  
Guardian Validation:
  ✓ Technical accuracy: 100% (7/7 claims verified)
  ✓ Clarity: Flesch-Kincaid 10 (target: ≤12)
  ✓ Tone violations: 3 detected
    - "We don't like slow tools" → "Terminal executes significantly faster"
    - "We prefer the terminal" → "Terminal-based execution provides measurably superior performance"
    - "This is very important" → "Prevents silent failures that often mask data loss"
  
Enricher Transformation:
  ✓ Problem Statement: GUI tools introduce bottlenecks and opacity
  ✓ Current State: Organizations struggle with slow cycles, vendor lock-in
  ✓ Solution: Terminal-First framework (speed, safety, portability, auditability)
  ✓ Outcomes: 60-80% speed improvement, 100% silent-failure elimination, zero data loss risk
  ✓ CTA: Schedule 30-minute Technical Workshop (3 specific time slots)
  
Output: 642-word Solution Brief (Markdown, production-ready)
```

### Audit Phase Output
```
Consensus: Raw 185-word internal doc successfully transformed into sales brief. 
           0% data loss. All 6 directives preserved and expanded into business value.

Success: Guardian tone corrections simple & effective (3 violations, 10-15 words each).
         Technical accuracy never compromised during tone refinement.

Bottleneck: None. Pipeline executed cleanly.

Learning: Framework documentation tends to contain subjective language. 
          Pre-populated Guardian filters reduce future correction time by ~70%.
          Insight captured to Knowledge Registry for future framework runs.
```

---



### Blueprint Mode
```
"Design an agent pipeline to score and enrich B2B leads."
```
The Orchestrator outputs a complete hierarchy.

### Deployment Mode
```
"Run this lead list through my Scout → Guardian → Enricher → Qualifier system."
```
The Orchestrator executes the chain, logs each step, and returns the result + execution log.

### Audit Mode
```
"Analyze the last execution. Where did it succeed? What should we refine?"
```
The Orchestrator synthesizes the log and proposes improvements.

---

## The SCOUT/GUARDIAN/ENRICHER Standard Pattern

This is the canonical three-agent pipeline. Use it as a reference for custom hierarchies.

| Agent     | Role                           | Input                | Output                      | Constraints                           |
|-----------|--------------------------------|----------------------|-----------------------------|---------------------------------------|
| **Scout** | Ingest raw data, flag issues   | Raw data (any format)| Normalized data + flags     | Accept all; no rejection               |
| **Guardian** | Validate against strict rules | Normalized data      | Valid data + rejection log  | Schema strict; fail on critical fields |
| **Enricher** | Transform to high-value output | Valid data           | Enriched data + metadata    | Succeed or escalate; no data loss     |

### Example: Lead Data Pipeline

1. **Scout** ingests CSV, JSON, API responses—flags weird date formats, missing names.
2. **Guardian** rejects records missing email_address (critical field), validates phone format.
3. **Enricher** looks up company_size, industry, adds confidence scores.
4. **Output** → 95 valid, enriched leads ready for sales routing.

---

## Constraints & Escalation Protocol

Every agent has **constraints**. When hit, the Escalation Protocol activates:

1. **Hard Constraint** (fatal): Agent cannot proceed. Task halts.
   - Example: Guardian requires email_address; 5 records lack it.
   - Action: Guardian rejects them, logs the reason, halts.
   - Escalation: Notifies Overseer with the rejected records.

2. **Soft Constraint** (warning): Agent can proceed but with reduced confidence.
   - Example: Enricher cannot find company_size for 3 records.
   - Action: Enricher marks them with `confidence: 0.7`, continues.
   - Escalation: Flags them in audit for manual review.

3. **Cascading Constraint**: One agent's output triggers another agent's input constraint.
   - Example: Guardian rejects records → Enricher receives fewer inputs than expected.
   - Action: Orchestrator logs the cascade, tracks % data loss.
   - Escalation: If loss exceeds threshold, escalate to Overseer.

---

## Execution Log Format

Every deployment mode run produces a standardized log:

```json
{
  "task_id": "lead_enrich_2025_04_03_001",
  "initiated_at": "2025-04-03T14:22:00Z",
  "status": "completed",
  "agents_executed": ["scout", "guardian", "enricher"],
  "timeline": [
    {
      "agent": "scout",
      "action": "ingest",
      "input_count": 100,
      "output_count": 100,
      "flags": ["2 date_format_weird", "3 name_missing"],
      "duration_ms": 245
    },
    {
      "agent": "guardian",
      "action": "validate",
      "input_count": 100,
      "output_count": 95,
      "rejections": {"missing_email": 5},
      "constraint_hit": "email_required",
      "duration_ms": 312
    },
    {
      "agent": "enricher",
      "action": "transform",
      "input_count": 95,
      "output_count": 95,
      "enrichments": {"company_size": 95, "industry": 95},
      "soft_constraints": ["company_size_not_found: 2"],
      "duration_ms": 1834
    }
  ],
  "escalations": [],
  "final_output_count": 95,
  "data_loss_pct": 5,
  "audit_notes": "5 records rejected for missing email. Consider pre-enrichment email lookup in Scout."
}
```

---

## Building Custom Hierarchies

Use the Blueprint Mode to design for your specific workflow. The Orchestrator will:

1. Analyze your goal.
2. Propose agent roles based on the steps required.
3. Define constraints that prevent invalid states.
4. Specify success metrics so you know when each agent is done.

Example: "Design agents for automated WordPress site generation using Elementor."

Expected output: Hierarchy with agents for Site Planning, Template Selection, Content Generation, Layout Assembly, Validation, Deployment.

---

## Key Principles

- **Clarity of Role**: Each agent owns one transformation. No overlap.
- **Explicit Handoff**: Data and state pass cleanly between agents.
- **Fail-Safe Design**: Constraints prevent bad data from cascading.
- **Auditability**: Every decision logged; no black boxes.
- **Refinement**: Learning loop ensures the architecture improves over time.

---

## When to Use This Skill

- You have a multi-step workflow that benefits from role-based orchestration.
- You need to design or refine a hierarchy of agents (human or AI).
- You're executing a task and want structured, auditable feedback on what each agent contributed.
- You're debugging why a workflow succeeded or failed and want to know exactly which agent caused it.
- You're building lead pipelines, content generation systems, data enrichment pipelines, or any intelligence workflow.

---

---

## The Contextual Memory Bridge — Knowledge Registry

Every Audit phase produces **learned patterns**. The Orchestrator stores these insights in a persistent **Knowledge Registry**, making future Blueprint designs adaptive rather than static.

### Live Example: Terminal-First Framework

In a real-world execution (task_id: `c2c_terminal_first_2025_04_03_001`), the Orchestrator processed raw technical documentation about terminal-based automation through the full pipeline:

**Input**: 185-word Markdown doc with subjective framing ("We prefer the terminal...")  
**Scout Output**: 6 core directives extracted, 3 ambiguity flags (subjective language detected)  
**Guardian Validation**: Identified 3 tone violations, all corrected. Technical accuracy: 100%.  
**Enricher Output**: 642-word Solution Brief (problem → solution → outcomes → specific CTA)  
**Overseer Synthesis**: 0% data loss, zero escalations, zero rework cycles.

**Learned Insight Captured** (`insight_003`):
```
Pattern: Framework documentation authored by practitioners contains subjective language 
("we prefer", "we like", "very important") that reduces sales credibility.

Solution: Guardian constraints flag subjective patterns; tone corrections are brief (10-15 words)
and highly effective.

Expected ROI: 70% reduction in tone correction time on future framework documentation runs.
Confidence: 96%.
```

This insight now informs future Blueprint designs for similar tasks. When processing technical framework docs, the Orchestrator pre-populates Guardian filters with known subjective-language patterns.

### How It Works

1. **Audit produces insights** — Example: "Email lookup in Scout reduces Guardian rejections by 70%."
2. **Insight stored** — Indexed by task type, domain, and success metric.
3. **Blueprint queries registry** — When designing a new hierarchy for a similar task, the Orchestrator retrieves relevant historical insights.
4. **Hierarchy improves** — New blueprints inherit successful patterns from previous runs.

### Knowledge Registry Structure

```json
{
  "registry_id": "content_and_lead_pipelines",
  "insights": [
    {
      "id": "insight_001",
      "task_type": "lead_enrichment",
      "learned_pattern": "Email pre-lookup in Scout reduces Guardian rejections by ~70%",
      "source_audit": "lead_enrich_2025_04_03_A",
      "success_rate": 0.87,
      "domains": ["B2B_sales", "lead_scoring"],
      "applicable_to": ["scout", "guardian"],
      "implementation": "Add email_lookup step using public API + company_name + title",
      "latency_cost_ms": 300,
      "roi_impact": "Net +64 leads per 50 records",
      "date_learned": "2025-04-03",
      "confidence": 0.92
    },
    {
      "id": "insight_002",
      "task_type": "content_transformation",
      "learned_pattern": "Guardian 'precision' constraint measured by: sentence_complexity_score < 0.7 AND jargon_density < 15%",
      "source_audit": "content_brief_2025_04_03_B",
      "success_rate": 0.94,
      "domains": ["technical_documentation", "sales_collateral"],
      "applicable_to": ["guardian", "enricher"],
      "implementation": "Use readability metrics (Flesch-Kincaid, jargon index) for validation",
      "confidence": 0.88
    }
  ],
  "query_function": "When designing a new Blueprint for [task_type], the Orchestrator retrieves all insights with `domains` matching the new task, then proposes constraints and patterns learned from similar previous runs."
}
```

### Blueprint Design with Registry

When you request a Blueprint, the Orchestrator:

1. Identifies the task type and domain.
2. Queries the Knowledge Registry for relevant insights.
3. Proposes agents with constraints informed by historical success.
4. **Cites the learned pattern** in the blueprint JSON.

**Example:**
```
"guardian": {
  "role": "Validate tone and precision",
  "constraints": [
    "sentence_complexity_score < 0.7 (learned from insight_002: 94% success rate)",
    "jargon_density < 15% (learned from insight_002)",
    "technical_accuracy: Verified against source (confidence > 0.85)"
  ]
}
```

---

## Glossary

- **Agent**: A defined role with constraints and success metrics.
- **Hierarchy**: The ordered sequence of agents in a workflow.
- **Handoff**: The point where one agent's output becomes the next agent's input.
- **Constraint**: A rule or limit that defines when an agent stops or escalates.
- **Escalation**: When a constraint is hit and a higher-tier agent is notified.
- **Execution Log**: The complete record of what happened during Deployment Mode.
- **Audit**: Reflective analysis of the execution log to identify improvements.
- **Overseer**: The highest-tier agent or user responsible for final synthesis and escalation resolution.
- **Knowledge Registry**: Persistent store of learned patterns and insights from historical Audit phases.
- **Contextual Memory Bridge**: The mechanism by which the Orchestrator queries the Registry to inform new Blueprint designs.
