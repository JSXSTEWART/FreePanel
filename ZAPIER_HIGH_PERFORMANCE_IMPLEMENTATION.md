# Zapier High-Performance Prompting: Complete Implementation Guide

A comprehensive resource for building production-grade Zapier workflows in FreePanel using Structured Engineering Prompts.

---

## What You've Built

### Documentation Suite (3 Core Guides)

1. **[ZAPIER_WORKFLOW_GITHUB_PR_COMPLEXITY_HIGH_PERFORMANCE.md](./ZAPIER_WORKFLOW_GITHUB_PR_COMPLEXITY_HIGH_PERFORMANCE.md)** (1,400+ lines)
   - **Use Case:** Automate PR review priority using AI-powered code complexity analysis
   - **Key Steps:** Extract metrics → OpenAI analysis → GitHub labeling → Comment posting → Slack alerts
   - **Tech Stack:** GitHub webhooks, OpenAI GPT-4, GitHub REST API
   - **Output:** 🏆 Ready for immediate implementation
   - **Complexity:** Advanced (6 steps, 2 APIs, conditional logic)

2. **[ZAPIER_WORKFLOW_LEAD_NORMALIZER_HIGH_PERFORMANCE.md](./ZAPIER_WORKFLOW_LEAD_NORMALIZER_HIGH_PERFORMANCE.md)** (1,200+ lines)
   - **Use Case:** Transform messy lead data into CRM-ready records
   - **Key Steps:** Email normalization → Name parsing → Phone standardization → Address parsing → Company deduplication → Duplicate detection → CRM insert/update
   - **Tech Stack:** OpenAI GPT-4 for company intelligence, HubSpot/Salesforce/Pipedrive CRM APIs
   - **Output:** 🏆 Ready for implementation
   - **Complexity:** Expert (9 steps, 2 external APIs, fuzzy matching)

3. **[ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md](./ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md)** (500+ lines)
   - **Use Case:** Quick reference for writing high-performance Zapier Code Steps
   - **Content:** 5 core principles, 3 templates (normalization, API integration, complex parsing), common mistakes, performance optimization
   - **Output:** 📖 Cheat sheet for rapid implementation
   - **Format:** Copy-paste prompt templates with examples

---

## The High-Performance Prompting Framework

### Core Concept

**Generic prompts** ("normalize this email") produce AI code that:
- ❌ Hallucinate variables that don't exist in `inputData`
- ❌ Crash on null/undefined fields instead of handling gracefully
- ❌ Return strings instead of objects, breaking the workflow
- ❌ Have no error handling or fallback logic
- ❌ Fail silently with no visibility into what went wrong

**Structured Engineering Prompts** (specific templates) produce code that:
- ✅ Explicitly declare all variables and their types
- ✅ Validate inputs with null checks and defaults
- ✅ Return well-defined objects with success/error flags
- ✅ Include try/catch blocks and API fallback logic
- ✅ Provide clear error messages for debugging

### The 5 Core Principles

#### 1. **InputData Strict Mode**
Explicitly map every variable from Zapier into your code:

```
❌ "Use the email to validate it."
✅ "The email is in inputData.email (string). 
   If empty, return error. 
   Default country is inputData.country_code (string, default 'US')."
```

#### 2. **Output Contract**
Specify the exact return structure:

```
❌ "Return the result."
✅ "Return an object: { success: true, email: '...', is_valid: true }
   If error, return: { success: false, error: 'message' }
   Do NOT throw errors."
```

#### 3. **Defensive Coding**
Handle failures gracefully:

```
❌ var parsed = JSON.parse(inputData.json);
✅ try { 
     var parsed = JSON.parse(inputData.json); 
   } catch (e) { 
     return { success: false, error: e.message }; 
   }
```

#### 4. **Null Checks & Defaults**
Always assume data might be missing:

```
❌ var name = inputData.name.toUpperCase();
✅ var name = (inputData.name || "").trim().toUpperCase();
```

#### 5. **Chain-of-Thought** (for complex logic)
Force the AI to plan before coding:

```
"Before writing code, write a COMMENT PLAN explaining:
1. How you'll split the CSV
2. How you'll handle quoted strings
3. Which fields you'll validate
4. What the output will look like

Then implement the plan."
```

---

## Implementation Roadmap

### Phase 1: Understand (1-2 hours)
1. Read [ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md](./ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md) — Get familiar with the 5 principles
2. Study one template (e.g., "Basic Normalization")
3. Review common mistakes section

### Phase 2: Implement One Workflow (4-6 hours)
Pick either workflow to build:

**Option A: GitHub PR Complexity Scorer** (Recommended for technical teams)
- Follow [ZAPIER_WORKFLOW_GITHUB_PR_COMPLEXITY_HIGH_PERFORMANCE.md](./ZAPIER_WORKFLOW_GITHUB_PR_COMPLEXITY_HIGH_PERFORMANCE.md)
- 6 steps, each with explicit prompt provided
- Test with sample PR webhook payloads
- Deploy to your FreePanel repository

**Option B: Lead Normalization** (Recommended for sales/marketing)
- Follow [ZAPIER_WORKFLOW_LEAD_NORMALIZER_HIGH_PERFORMANCE.md](./ZAPIER_WORKFLOW_LEAD_NORMALIZER_HIGH_PERFORMANCE.md)
- 9 steps, with prompts for each
- Test with messy sample lead data (typos, formatting errors)
- Deploy to your CRM (HubSpot, Salesforce, etc.)

### Phase 3: Customize & Optimize (2-3 hours)
- Adjust complexity thresholds or validation rules
- Add team-specific fields or integrations
- Monitor execution times and costs
- Set up logging/alerting for failures

### Phase 4: Scale to More Workflows (Ongoing)
- Use templates from Reference guide to build similar workflows
- Document lessons learned
- Share configurations with your team

---

## Comparison: Before & After

### Example: Email Normalization Step

#### ❌ Generic Prompt (Fails in Production)

```
User writes to Zapier AI:
"Normalize this email and return it lowercase."

AI produces:
output = inputData.email.toLowerCase();
// No validation, no error handling, crashes if email is null
```

**Result:** Workflow crashes on 10-15% of submissions with null email.

#### ✅ Structured Engineering Prompt (Production-Grade)

```
User provides explicit prompt:
"The email is in inputData.email (string, may be empty).
If empty, return { success: false, error: 'Email required' }.
Trim whitespace, convert to lowercase, validate format with regex.
Return { success: true, email_normalized: '...', is_valid: true }"

AI produces:
var email = (inputData.email || "").trim().toLowerCase();
if (!email) {
  return { success: false, error: "Email required" };
}
var isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
return { success: true, email_normalized: email, is_valid: isValid };
// Complete error handling, handles all edge cases
```

**Result:** Workflow handles all cases gracefully, no crashes.

---

## Quick Start: Copy-Paste Templates

### Template 1: Email Validation (5 minutes)

Go to [ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md](./ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md) → "Template: Basic Normalization"

Fill in blanks:
- `{FIELD_NAME}` → "Email"
- `{field_name}` → "email"
- `[INSERT NORMALIZATION LOGIC]` → `value.toLowerCase()`
- `[INSERT VALIDATION LOGIC]` → `/^[^\s@]+@[^\s@]+\.[^\s@]+$/`

Copy into Zapier Code Step. Done.

### Template 2: OpenAI API Call (10 minutes)

Go to [ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md](./ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md) → "Template: API Integration with Fallback"

Fill in:
- `{API_NAME}` → "OpenAI"
- URL → `https://api.openai.com/v1/chat/completions`
- Request body → Your prompt

Copy into Zapier Code Step. Done.

### Template 3: CSV Parsing (15 minutes)

Go to [ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md](./ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md) → "Template: Complex Parsing (Chain-of-Thought)"

Customize for your CSV structure (headers, data types, validation rules).

Copy into Zapier Code Step. Done.

---

## Real-World Examples

### Example 1: GitHub PR Complexity Scorer

**Workflow:** GitHub PR opened → Extract metrics → OpenAI analysis → Apply labels → Post comment

**Key Code Steps:**
1. **Step 1: Extract Metrics** — Safely extract additions, deletions, file count from webhook
2. **Step 2: OpenAI Analysis** — Query GPT-4, handle rate limits with fallback
3. **Step 3: Label Mapping** — Convert complexity to GitHub labels
4. **Step 5: Generate Comment** — Build markdown comment with recommendations

**High-Performance Techniques Used:**
- ✅ Explicit inputData mapping (all 7 webhook fields named)
- ✅ Output contract (exact object structure for each step)
- ✅ Defensive coding (try/catch on OpenAI API)
- ✅ Rate limit fallback (if 429, use heuristic analysis)
- ✅ Chain-of-thought (comment plan for OpenAI prompt construction)

**Result:** Robust workflow that handles:
- Missing PR fields (uses defaults)
- OpenAI rate limits (falls back to heuristic)
- API errors (graceful degradation)
- Edge cases (all-zeros PR metrics, empty descriptions)

### Example 2: Lead Normalization

**Workflow:** Form submission → Normalize email → Parse name → Format phone → Standardize address → Lookup company → Check duplicates → CRM insert/update

**Key Code Steps:**
1. **Step 1: Email Normalization** — Handle Gmail aliases, detect disposable domains
2. **Step 2: Name Parsing** — Detect "Last, First" vs "First Last", extract salutation
3. **Step 3: Phone Standardization** — Extract digits, validate length, format for country
4. **Step 4: Address Parsing** — State abbreviation lookup, zip validation
5. **Step 5: Company Deduplication** — OpenAI + fuzzy match against known companies
6. **Step 6: Duplicate Detection** — Query CRM, calculate merge score

**High-Performance Techniques Used:**
- ✅ Defensive null checks (every step handles missing optional fields)
- ✅ Fallback logic (if OpenAI unavailable, use heuristic company matching)
- ✅ Data type conversion (string phone → number, validate)
- ✅ Chain-of-thought (CSV parsing with quoted strings)
- ✅ Error object returns (no exceptions thrown)

**Result:** Clean, deduped, CRM-ready lead records from messy form submissions

---

## Performance Metrics

### Execution Time Targets

| Step Type | Target | Notes |
|-----------|--------|-------|
| Normalization (email, phone) | <1 sec | No API calls, pure logic |
| API Call (OpenAI, HubSpot) | 2-5 sec | Includes network latency |
| Complex Parsing (CSV, address) | 1-3 sec | Depends on input size |
| Full Workflow (6+ steps) | 5-10 sec | Zapier timeout is 10 sec for most plans |

### Cost Estimates

| Component | Cost/Month | Notes |
|-----------|-----------|-------|
| Zapier | $19-99 | Depends on task count |
| OpenAI (GPT-4) | $5-20 | ~$0.02 per analysis |
| GitHub API | Free | Generous rate limits |
| CRM (HubSpot) | $50+ | Depends on contacts |
| **Total** | **~$100-150** | For typical setup |

---

## Troubleshooting Guide

### Error: "Cannot read property 'email' of undefined"

**Root Cause:** `inputData.email` is not mapped from Zapier trigger.

**Fix:** 
1. Check Zapier trigger output → Click "Test" to see actual data structure
2. Verify field mapping in Code Step's input section
3. Use fallback: `var email = (inputData.email || "").trim();`

---

### Error: "output is not a valid object"

**Root Cause:** Code returns string, array, or null instead of object.

**Fix:**
```javascript
// ❌ Wrong
output = "success";

// ✅ Right
output = { success: true, message: "Operation completed" };
```

---

### Error: "API request timed out"

**Root Cause:** OpenAI or external API is slow/rate-limited.

**Fix:** Implement fallback logic in code:
```javascript
if (response.status === 429) {
  return { success: true, api_used: "fallback", result: fallbackValue };
}
```

---

### Error: "Duplicate leads being created"

**Root Cause:** Duplicate detection step not running, or merge_score threshold too high.

**Fix:**
1. Verify Step 6 (duplicate check) is enabled
2. Lower merge_score threshold from 0.9 to 0.8 or 0.7
3. Check CRM API key is valid

---

## Next Steps

### Recommended Reading Order

1. **Start here:** [ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md](./ZAPIER_STRUCTURED_PROMPTS_REFERENCE.md) (20 min read)
   - Learn the 5 principles
   - See 3 templates
   - Review common mistakes

2. **Pick a workflow:** [ZAPIER_WORKFLOW_GITHUB_PR_COMPLEXITY_HIGH_PERFORMANCE.md](./ZAPIER_WORKFLOW_GITHUB_PR_COMPLEXITY_HIGH_PERFORMANCE.md) or [ZAPIER_WORKFLOW_LEAD_NORMALIZER_HIGH_PERFORMANCE.md](./ZAPIER_WORKFLOW_LEAD_NORMALIZER_HIGH_PERFORMANCE.md) (1 hour read)
   - Deep dive into implementation
   - Study all code step prompts
   - Review testing procedures

3. **Build & test:** Implement one workflow end-to-end (4-6 hours)
   - Set up Zapier account
   - Create webhook trigger
   - Add each step with prompt from guide
   - Test with sample data

4. **Scale:** Build additional workflows using templates as reference

---

## Advanced Topics

### A/B Testing Complexity Thresholds

In GitHub PR Complexity Scorer, you can tune when PRs are flagged as "high":

```javascript
// Conservative: Flag >30 files or >250 lines
if (metrics.files_changed > 30 || metrics.lines_changed > 250) {
  complexity = "high";
}

// Aggressive: Flag >50 files or >500 lines
if (metrics.files_changed > 50 || metrics.lines_changed > 500) {
  complexity = "high";
}
```

Measure impact: Do high-flagged PRs actually have more bugs? Adjust thresholds quarterly.

---

### Custom Company Database

For lead normalization, pre-populate a "known companies" list:

```json
[
  { "name": "Acme Corporation", "domain": "acmecorp.com", "id": 123 },
  { "name": "Tech Startup Inc", "domain": "techstartup.io", "id": 124 },
  ...
]
```

Pass to Step 5 (Company Normalization) for fast exact matching before hitting OpenAI.

---

### Multi-Language Support

Extend Lead Normalizer to handle international addresses:

```javascript
var country = inputData.country_code || "US";

if (country === "GB") {
  // UK postcode format: "SW1A 1AA"
  zip = zip.replace(/\s+/g, " ").toUpperCase();
} else if (country === "CA") {
  // Canadian postal code format: "K1A 0B1"
  zip = zip.replace(/\s+/g, " ").toUpperCase();
}
```

---

## Support & Resources

- **[Zapier Code Step Documentation](https://zapier.com/help/doc/code)**
- **[OpenAI API Reference](https://platform.openai.com/docs/api-reference)**
- **[GitHub REST API](https://docs.github.com/en/rest)**
- **[FreePanel Webhook Security Guide](./WEBHOOK_SECURITY.md)** — HMAC-SHA256 signature verification

---

## Changelog

### Version 1.0 (December 20, 2025)
- ✅ Created GitHub PR Complexity Scorer workflow (1,400+ lines, 8 steps)
- ✅ Created Lead Normalization workflow (1,200+ lines, 9 steps)
- ✅ Created Structured Prompts Reference (500+ lines, 3 templates)
- ✅ Documented 5 core high-performance principles
- ✅ Added real-world examples, testing procedures, troubleshooting

### Planned Features
- [ ] CSV to Line Items workflow
- [ ] Lead Normalization v2 with fuzzy matching (Levenshtein distance)
- [ ] Advanced monitoring dashboard
- [ ] Integration with Jira, Microsoft Teams, Slack
- [ ] Cost optimization guide (OpenAI token counting)

---

**Last Updated:** December 20, 2025  
**Maintained By:** FreePanel Zapier Integration Team  
**License:** MIT
