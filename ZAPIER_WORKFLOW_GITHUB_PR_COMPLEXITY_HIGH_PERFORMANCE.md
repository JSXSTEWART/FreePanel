# Zapier Workflow: GitHub PR Complexity Scorer
## High-Performance Edition with Structured Engineering Prompts

This guide applies **Structured Engineering Prompts** to the GitHub PR Complexity Scorer workflow, enabling robust handling of API failures, rate limits, malformed data, and edge cases.

---

## Executive Summary: Why This Matters

**The Problem:** Generic Zapier prompts ("analyze PR complexity") result in:
- AI hallucinating variables that don't exist in `inputData`
- Code that crashes on missing fields instead of gracefully degrading
- No handling of GitHub/OpenAI API failures or rate limits
- Brittle parsing logic that fails on unexpected JSON structures

**The Solution:** Structured Engineering Prompts with:
1. **Strict InputData declarations** — explicit variable mapping
2. **Output contracts** — exact return structure required
3. **Defensive coding** — try/catch blocks, null checks, error objects
4. **Chain-of-thought** — comment-driven planning before implementation

---

## Architecture Overview

```
GitHub Webhook (PR opened/updated)
     ↓
[STEP 1] Extract PR Metrics (Code Step)
     ↓ inputData: {pr_number, repo, additions, deletions, files_changed, commits}
     ↓ output: {metrics: {...}, error: null}
     ↓
[STEP 2] OpenAI Analysis (Code Step + API)
     ↓ inputData: {metrics, openai_key, model, max_tokens}
     ↓ output: {complexity: 'low|medium|high', reasoning: string, error: null}
     ↓
[STEP 3] Map Complexity to Labels (Code Step)
     ↓ inputData: {complexity, risk_factors: []}
     ↓ output: {labels: [...], color: string}
     ↓
[STEP 4] GitHub Label Application (GitHub API)
     ↓ inputData: {repo, pr_number, labels}
     ↓
[STEP 5] Generate Comment (Code Step)
     ↓ inputData: {complexity, reasoning, recommendations}
     ↓ output: {comment_body: string}
     ↓
[STEP 6] Post Comment (GitHub API)
     ↓ inputData: {repo, pr_number, comment_body}
     ↓
[Optional] Slack/Teams Notification (if complexity === 'high')
```

---

# Detailed Implementation

## Step 1: Extract PR Metrics (Code Step)

### Purpose
Safely extract and validate PR metrics from the GitHub webhook payload, with fallback values for missing fields.

### Zapier Setup
1. Create a **Code by Zapier** step (JavaScript)
2. Map the following input variables:
   ```
   pr_number → pull_request.number
   repo_full_name → repository.full_name
   additions → pull_request.additions
   deletions → pull_request.deletions
   files_changed → pull_request.changed_files
   commits → pull_request.commits
   title → pull_request.title
   body → pull_request.body
   author → pull_request.user.login
   base_ref → pull_request.base.ref
   head_ref → pull_request.head.ref
   ```

### High-Performance Prompt

```
Role: You are a Senior Node.js Data Validation Engineer.

Task: Extract and validate PR metrics from a GitHub webhook payload.

Input Mapping (Strict Mode):
The following variables are available in the inputData object:
- inputData.pr_number (integer, the PR number)
- inputData.repo_full_name (string, e.g., "JSXSTEWART/FreePanel")
- inputData.additions (integer, lines added, default 0)
- inputData.deletions (integer, lines deleted, default 0)
- inputData.files_changed (integer, files modified, default 0)
- inputData.commits (integer, total commits, default 1)
- inputData.title (string, PR title)
- inputData.body (string, PR description, may be empty or null)

Defensive Requirements:
1. Treat all numeric fields as potentially null/undefined. Use zero as fallback.
2. If inputData.title is missing, use "Untitled PR".
3. Trim and validate inputData.repo_full_name. If it doesn't contain "/", return an error.

Computation:
1. Calculate complexity_score using these metrics:
   - lines_changed = additions + deletions
   - files_per_commit = files_changed / commits (min 1)
   - complexity_score = (lines_changed * 0.1) + (files_changed * 0.3) + (files_per_commit * 0.2)

2. Classify risk level:
   - "low" if complexity_score < 20 AND files_changed <= 5 AND lines_changed <= 100
   - "high" if complexity_score > 50 OR files_changed > 20 OR lines_changed > 500
   - "medium" otherwise

3. Flag these risk factors (store in an array):
   - "large_changeset" if lines_changed > 300
   - "many_files" if files_changed > 15
   - "high_commit_count" if commits > 10
   - "merge_conflict_risk" if deletions > additions (indicates possible conflict resolution)
   - "documentation_missing" if body is empty or null

Output Contract (REQUIRED):
Assign to the 'output' variable an object with this exact structure:
{
  "success": true,
  "pr_number": 42,
  "repo": "JSXSTEWART/FreePanel",
  "metrics": {
    "additions": 245,
    "deletions": 87,
    "lines_changed": 332,
    "files_changed": 8,
    "commits": 5,
    "complexity_score": 34.2
  },
  "risk_level": "medium",
  "risk_factors": ["large_changeset", "many_files"],
  "title": "feat: Add user authentication",
  "author": "developer-name"
}

Error Handling:
If any error occurs, catch it and return:
{
  "success": false,
  "error": error.message,
  "pr_number": inputData.pr_number || "unknown"
}

Do NOT throw an error. Always return an object.
```

### Example Output
```json
{
  "success": true,
  "pr_number": 42,
  "repo": "JSXSTEWART/FreePanel",
  "metrics": {
    "additions": 245,
    "deletions": 87,
    "lines_changed": 332,
    "files_changed": 8,
    "commits": 5,
    "complexity_score": 34.2
  },
  "risk_level": "medium",
  "risk_factors": ["large_changeset", "many_files"],
  "title": "feat: Add user authentication",
  "author": "developer-name"
}
```

---

## Step 2: OpenAI Complexity Analysis (Code Step + API Call)

### Purpose
Query OpenAI GPT-4 to generate intelligent complexity reasoning, with rate-limit awareness and fallback logic.

### Zapier Setup
1. Create a **Code by Zapier** step (JavaScript)
2. Map input variables:
   ```
   openai_api_key → (from Zapier Env Variable)
   complexity_score → output.metrics.complexity_score (from Step 1)
   risk_factors → output.risk_factors (from Step 1)
   title → output.title (from Step 1)
   additions → output.metrics.additions (from Step 1)
   deletions → output.metrics.deletions (from Step 1)
   files_changed → output.metrics.files_changed (from Step 1)
   ```

### High-Performance Prompt

```
Role: You are a Senior Node.js Backend Engineer building a production API client.

Task: Query OpenAI GPT-4 to analyze PR complexity with fallback logic for rate limits and API errors.

Input Mapping (Strict Mode):
The following variables are available in inputData:
- inputData.openai_api_key (string, your OpenAI API key)
- inputData.complexity_score (number, calculated complexity metric)
- inputData.risk_factors (array of strings, e.g., ["large_changeset", "many_files"])
- inputData.title (string, PR title)
- inputData.additions (number, lines added)
- inputData.deletions (number, lines deleted)
- inputData.files_changed (number, files modified)

Defensive Requirements:
1. Check that inputData.openai_api_key is not empty. If missing, return:
   { success: false, error: "Missing OpenAI API key" }
2. If inputData.complexity_score is undefined, default to 25.
3. If inputData.risk_factors is not an array, treat as empty array [].
4. Wrap all fetch calls in try/catch blocks.
5. Implement retry logic: if you get a 429 (rate limit) error, return a fallback analysis instead of failing.

Implementation (Chain of Thought):
// COMMENT PLAN:
// 1. Build the OpenAI prompt with PR metrics and risk factors
// 2. Prepare fetch request with authorization header
// 3. Make POST call to https://api.openai.com/v1/chat/completions
// 4. Parse response and extract reasoning from assistant's message
// 5. If API fails, use fallback logic based on complexity_score
// 6. Return result with success flag and analysis details

Build System Prompt:
var systemPrompt = "You are a code review expert. Analyze pull requests for complexity, risk, and maintainability.";

Build User Prompt (include all metrics):
var userPrompt = "Analyze this PR for complexity:\n" +
  "Title: " + inputData.title + "\n" +
  "Lines Added: " + inputData.additions + "\n" +
  "Lines Deleted: " + inputData.deletions + "\n" +
  "Files Changed: " + inputData.files_changed + "\n" +
  "Risk Factors: " + JSON.stringify(inputData.risk_factors) + "\n\n" +
  "Provide a 2-3 sentence analysis, then end with exactly one of: COMPLEXITY:LOW, COMPLEXITY:MEDIUM, COMPLEXITY:HIGH";

API Call with Error Handling:
- Endpoint: POST https://api.openai.com/v1/chat/completions
- Headers: { "Authorization": "Bearer " + inputData.openai_api_key, "Content-Type": "application/json" }
- Body: { model: "gpt-4", max_tokens: 300, messages: [{role: "system", content: systemPrompt}, {role: "user", content: userPrompt}] }
- Timeout: 15 seconds
- On 429 error (rate limit): skip API and use fallback classification
- On other errors: log and use fallback

Fallback Logic (if API fails or rate limited):
var complexityMap = {
  "low": "low",
  "medium": "medium",
  "high": "high"
};

if (inputData.complexity_score < 20) {
  var fallbackAnalysis = "Automated fallback: Small, focused change.";
  var fallbackLevel = "low";
} else if (inputData.complexity_score > 50) {
  var fallbackAnalysis = "Automated fallback: Large, complex change requiring careful review.";
  var fallbackLevel = "high";
} else {
  var fallbackAnalysis = "Automated fallback: Moderate complexity change.";
  var fallbackLevel = "medium";
}

Output Contract (REQUIRED):
Assign to 'output' an object with this exact structure:
{
  "success": true,
  "complexity": "low|medium|high",
  "reasoning": "Human-readable analysis (from GPT-4 or fallback)",
  "api_used": "openai" | "fallback",
  "risk_summary": "String with 1-2 key concerns",
  "recommendations": ["Code review by senior dev", "Add unit tests", ...]
}

If API call fails:
{
  "success": false,
  "error": error.message,
  "complexity": "medium",
  "api_used": "fallback"
}

Do NOT throw an error. Always return an object with at least a success field and complexity field.
```

### Example Output
```json
{
  "success": true,
  "complexity": "medium",
  "reasoning": "This change touches 8 files with 332 lines modified, introducing OAuth2 authentication. The scope is well-defined but requires careful testing of security implications.",
  "api_used": "openai",
  "risk_summary": "Security-critical authentication changes require senior review and comprehensive tests",
  "recommendations": [
    "Schedule code review with security engineer",
    "Add unit tests for OAuth2 flows",
    "Document API breaking changes",
    "Test edge cases (token expiry, refresh)"
  ]
}
```

---

## Step 3: Map Complexity to GitHub Labels (Code Step)

### Purpose
Convert complexity classification to GitHub-compatible labels with colors for visibility.

### Zapier Setup
1. Create a **Code by Zapier** step
2. Map input variables:
   ```
   complexity → output.complexity (from Step 2)
   risk_factors → output.risk_factors (from Step 1)
   api_used → output.api_used (from Step 2)
   ```

### High-Performance Prompt

```
Role: You are a GitHub automation engineer.

Task: Map PR complexity classification to GitHub labels and assign colors.

Input Mapping (Strict Mode):
The following variables are in inputData:
- inputData.complexity (string, one of: "low", "medium", "high")
- inputData.risk_factors (array of strings, may be empty)
- inputData.api_used (string, "openai" or "fallback")

Validation:
1. If inputData.complexity is not one of the 3 values, default to "medium".
2. If inputData.risk_factors is not an array, treat as empty array.
3. Normalize complexity to lowercase.

Label Mapping Logic:
Base labels by complexity:
- "low": ["complexity:low", "status:ready-to-merge"]
- "medium": ["complexity:medium", "needs-review"]
- "high": ["complexity:high", "status:needs-senior-review", "high-priority"]

Add conditional labels based on risk_factors:
- If risk_factors includes "large_changeset": add "large-changeset"
- If risk_factors includes "many_files": add "many-files"
- If risk_factors includes "merge_conflict_risk": add "merge-conflict-risk"
- If risk_factors includes "documentation_missing": add "docs-needed"
- If api_used === "fallback": add "ai-analysis:fallback"

Color mapping (GitHub label colors):
- Complexity-low: "#0366d6" (blue)
- Complexity-medium: "#fbca04" (yellow)
- Complexity-high: "#cb2431" (red)
- Status labels: "#6f42c1" (purple)
- Risk labels: "#ff7043" (orange)

Output Contract (REQUIRED):
Assign to 'output' an object with this exact structure:
{
  "success": true,
  "complexity": "low|medium|high",
  "labels": ["complexity:low", "status:ready-to-merge", ...],
  "label_count": 3,
  "colors": {
    "complexity:low": "#0366d6",
    "complexity:medium": "#fbca04",
    "complexity:high": "#cb2431"
  }
}

Error Handling:
If an error occurs, return:
{
  "success": false,
  "error": error.message,
  "labels": ["complexity:medium"]
}
```

### Example Output
```json
{
  "success": true,
  "complexity": "medium",
  "labels": [
    "complexity:medium",
    "needs-review",
    "large-changeset",
    "many-files"
  ],
  "label_count": 4,
  "colors": {
    "complexity:medium": "#fbca04",
    "large-changeset": "#ff7043"
  }
}
```

---

## Step 4: Apply Labels to GitHub PR (GitHub API)

### Zapier Setup
1. Use **GitHub** integration (not code)
2. Select action: "Update Issue"
3. Configure:
   ```
   Repository: JSXSTEWART/FreePanel (or dynamic from webhook)
   Issue Number: pull_request.number (from webhook)
   Labels: (map Step 3 output.labels array)
   ```

### Configuration Example
```
Repository: [GitHub Trigger] Full Name
Issue Number: [GitHub Trigger] Number
Labels: [Step 3] labels
```

---

## Step 5: Generate Comment with Recommendations (Code Step)

### Purpose
Build a well-formatted comment with complexity analysis, risk factors, and actionable recommendations.

### Zapier Setup
1. Create a **Code by Zapier** step
2. Map input variables:
   ```
   complexity → output.complexity (from Step 2)
   reasoning → output.reasoning (from Step 2)
   recommendations → output.recommendations (from Step 2)
   author → output.author (from Step 1)
   files_changed → output.metrics.files_changed (from Step 1)
   pr_title → output.title (from Step 1)
   risk_factors → output.risk_factors (from Step 1)
   ```

### High-Performance Prompt

```
Role: You are a GitHub bot developer specializing in clean, actionable comment formatting.

Task: Generate a professional GitHub comment with complexity analysis and recommendations.

Input Mapping (Strict Mode):
The following variables are in inputData:
- inputData.complexity (string, "low"|"medium"|"high")
- inputData.reasoning (string, AI-generated analysis)
- inputData.recommendations (array of strings, action items)
- inputData.author (string, PR author's GitHub handle)
- inputData.files_changed (number, count of modified files)
- inputData.pr_title (string, the PR title)
- inputData.risk_factors (array, ["large_changeset", ...])

Validation:
1. If inputData.reasoning is empty, use: "Unable to generate analysis at this time."
2. If inputData.recommendations is not an array, treat as [].
3. If inputData.complexity is missing, default to "medium".

Comment Structure Plan (COMMENT APPROACH):
// 1. Add header with bot name and complexity badge
// 2. Add reasoning paragraph
// 3. List risk factors (if any)
// 4. List recommendations in checklist format
// 5. Add footer with timestamp and API info
// 6. Ensure markdown is properly formatted for GitHub

Build Markdown Comment:
var header = "## 🤖 AI Complexity Analysis\n\n";
var badge = "**Complexity:** ";
if (inputData.complexity === "low") {
  badge += "🟢 LOW";
} else if (inputData.complexity === "high") {
  badge += "🔴 HIGH";
} else {
  badge += "🟡 MEDIUM";
}
badge += "\n\n";

var reasoning = "### Analysis\n" + inputData.reasoning + "\n\n";

var riskSection = "";
if (inputData.risk_factors && inputData.risk_factors.length > 0) {
  riskSection = "### ⚠️ Risk Factors\n";
  inputData.risk_factors.forEach(function(factor) {
    riskSection += "- " + factor + "\n";
  });
  riskSection += "\n";
}

var recSection = "### ✅ Recommended Actions\n";
if (inputData.recommendations && inputData.recommendations.length > 0) {
  inputData.recommendations.forEach(function(rec) {
    recSection += "- [ ] " + rec + "\n";
  });
} else {
  recSection += "- [ ] Standard code review\n";
}
recSection += "\n";

var footer = "---\n" +
  "Generated by FreePanel Complexity Scorer · " +
  "Files: " + inputData.files_changed + " · " +
  "Report an issue [here](https://github.com/JSXSTEWART/FreePanel/issues)\n";

var fullComment = header + badge + reasoning + riskSection + recSection + footer;

Output Contract (REQUIRED):
Assign to 'output' an object with this exact structure:
{
  "success": true,
  "comment_body": "## 🤖 AI Complexity Analysis\n\n**Complexity:** 🟡 MEDIUM\n...",
  "length": 847,
  "sections": ["header", "badge", "reasoning", "risks", "recommendations", "footer"]
}

Error Handling:
If an error occurs:
{
  "success": false,
  "error": error.message,
  "comment_body": "Unable to generate analysis comment."
}
```

### Example Comment Output
```markdown
## 🤖 AI Complexity Analysis

**Complexity:** 🟡 MEDIUM

### Analysis
This change touches 8 files with 332 lines modified, introducing OAuth2 authentication. The scope is well-defined but requires careful testing of security implications.

### ⚠️ Risk Factors
- large_changeset
- many_files
- merge_conflict_risk

### ✅ Recommended Actions
- [ ] Code review by security engineer
- [ ] Add unit tests for OAuth2 flows
- [ ] Document API breaking changes
- [ ] Test edge cases (token expiry, refresh)

---
Generated by FreePanel Complexity Scorer · Files: 8 · Report an issue [here](https://github.com/JSXSTEWART/FreePanel/issues)
```

---

## Step 6: Post Comment to GitHub PR (GitHub API)

### Zapier Setup
1. Use **GitHub** integration
2. Select action: "Create Issue Comment"
3. Configure:
   ```
   Repository: [GitHub Trigger] Full Name
   Issue Number: [GitHub Trigger] Number
   Comment Body: [Step 5] comment_body
   ```

---

## Step 7: Optional - Alert on High Complexity

### Zapier Setup: Conditional Logic

1. Add a **Filter** step:
   ```
   Condition: If [Step 2] complexity equals "high"
   Action: Continue
   ```

2. Add **Slack** or **Teams** action:
   ```
   Channel: #pr-reviews or @channel
   Message: "@here High-complexity PR from @{author}: {title}"
   ```

### Slack Message Template
```
🚨 High-Complexity Pull Request Detected

**PR:** #{pr_number} - {title}
**Author:** @{author}
**Files Changed:** {files_changed}
**Complexity:** 🔴 HIGH

**Key Risks:**
{risk_factors_list}

**Link:** {github_pr_link}
```

---

# Testing & Validation

## Local Testing with curl

### Test Input Data (Simulate GitHub Webhook)
```bash
cat > /tmp/pr_payload.json <<'EOF'
{
  "action": "opened",
  "pull_request": {
    "number": 42,
    "title": "feat: Add user authentication",
    "body": "This adds OAuth2 support",
    "user": {"login": "developer-name"},
    "additions": 245,
    "deletions": 87,
    "changed_files": 8,
    "commits": 5
  },
  "repository": {
    "name": "FreePanel",
    "full_name": "JSXSTEWART/FreePanel"
  }
}
EOF

# Trigger Zapier webhook (get URL from Zapier trigger settings)
curl -X POST https://hooks.zapier.com/hooks/catch/... \
  -H "Content-Type: application/json" \
  -d @/tmp/pr_payload.json
```

## Test Each Code Step

### Step 1: Extract Metrics
**Test with missing fields:**
```json
{
  "pr_number": 50,
  "repo_full_name": "JSXSTEWART/FreePanel",
  "additions": null,
  "deletions": null,
  "files_changed": 0,
  "commits": 1,
  "title": "Quick fix",
  "body": null
}
```

**Expected Output:**
```json
{
  "success": true,
  "metrics": {
    "additions": 0,
    "deletions": 0,
    "complexity_score": 0
  },
  "risk_level": "low",
  "risk_factors": ["documentation_missing"]
}
```

### Step 2: OpenAI Analysis
**Test rate-limit fallback:**
```json
{
  "openai_api_key": "invalid-key-to-trigger-error",
  "complexity_score": 50,
  "risk_factors": ["large_changeset"],
  "title": "Complex feature",
  "additions": 400,
  "deletions": 150,
  "files_changed": 20
}
```

**Expected Output:** Returns fallback complexity with `"api_used": "fallback"`

### Step 3: Label Mapping
**Test high complexity:**
```json
{
  "complexity": "high",
  "risk_factors": ["many_files", "large_changeset"],
  "api_used": "openai"
}
```

**Expected Output:**
```json
{
  "labels": ["complexity:high", "status:needs-senior-review", "high-priority", "many-files", "large-changeset"],
  "label_count": 5
}
```

---

# Troubleshooting Guide

## Code Step Failures

| Error | Root Cause | Fix |
|-------|-----------|-----|
| `Cannot read property 'pr_number' of undefined` | `inputData` is null/empty | Check Zapier input mapping from trigger. Ensure fields are mapped explicitly. |
| `API request timeout after 15s` | OpenAI slow/rate limited | Increase timeout to 30s, or rely on fallback logic. |
| `output.labels is not an array` | Code returns string instead of object | Verify code ends with `return` and `output` is an object, not string. |
| `GitHub API: Validation Failed: Label "custom label" doesn't exist` | Label not pre-created in repo | Pre-create labels in GitHub Settings → Labels before running Zap. |

## GitHub API Errors

| Error | Root Cause | Fix |
|-------|-----------|-----|
| `401 Unauthorized` | GitHub token expired | Reconnect GitHub integration in Zapier. |
| `404 Not Found` | Wrong repo or PR number | Verify repo full name and PR number in mapping. |
| `422 Unprocessable Entity` | Invalid label name (spaces, special chars) | Use kebab-case only: `complexity:high`, not `Complexity: High`. |

## Testing Checklist

- [ ] Trigger fires when PR is opened in GitHub
- [ ] Step 1 extracts metrics with non-null values
- [ ] Step 2 queries OpenAI (or uses fallback)
- [ ] Step 3 generates 3–5 labels
- [ ] Step 4 successfully applies labels to PR
- [ ] Step 5 generates markdown comment without errors
- [ ] Step 6 posts comment visible on PR
- [ ] Slack/Teams notification fires only for high complexity
- [ ] Fallback logic works when OpenAI API unavailable

---

# Configuration Examples

## Enterprise Conservative (Heavy Code Review)

**Goal:** Flag everything for human review, minimal AI automation.

```
Step 2 Prompt Addition:
"For this organization, prefer COMPLEXITY:HIGH over COMPLEXITY:MEDIUM when uncertain.
Assume security implications even on small changes. Include 'security-review' in recommendations."

Step 3 Labels:
Add "requires-cto-review" if complexity is not "low"

Step 4 & 6:
Add @architecture team as reviewer on all non-low PRs
```

## Agile Startup (Fast Merge)

**Goal:** Reduce friction, auto-approve low complexity, focus on high-risk.

```
Step 3 Labels:
Remove "needs-review" from "medium" complexity
Add "auto-mergeable" to "low" complexity PRs

Step 7 Slack Alert:
Only fire for "high" complexity (skip "medium")

GitHub Branch Protection:
Set "complexity:high" PRs to require 2 approvals
Set others to require 0 approvals (fast merge)
```

## ML-Focused Team (Data Pipeline)

**Goal:** Focus on ML safety, data changes, reproducibility.

```
Step 2 Prompt Modification:
"This is a data pipeline repository. Flag PRs that modify:
- SQL queries
- Data transformation logic
- Model training scripts
- Feature engineering code

For these, always recommend: 'Verify data lineage', 'Check for data drift', 'Test on dev dataset'"

Step 5 Comment:
Add section: "## 🧪 ML Testing Checklist"
```

---

# Key Takeaways: High-Performance Prompting

1. **Strict InputData Declarations**
   - Every variable is explicitly named with type (string, number, array, object)
   - No hallucination of missing fields
   - Fallback values specified upfront

2. **Output Contracts**
   - AI code must always return an object with `success` and `error` fields
   - Never throw exceptions; return error objects instead
   - Exact structure specified in prompt

3. **Defensive Coding**
   - Try/catch on all API calls
   - Null checks before accessing nested properties
   - Fallback logic for API failures (OpenAI rate limit → static analysis)

4. **Chain-of-Thought Planning**
   - Complex logic uses comment-driven planning before code
   - Forces AI to reason through edge cases
   - Reduces hallucinations and off-by-one errors

5. **Zapier-Specific Limits**
   - 1–10 second execution timeout (use parallel calls or defer work)
   - 128MB memory limit (don't load 50MB PDFs)
   - Standard libraries only (crypto, util, url, fetch)
   - No npm install; use built-in modules

---

# Next Steps

1. **Create GitHub Labels** (if not already present):
   ```bash
   gh label create complexity:low --color 0366d6 --description "Low complexity change"
   gh label create complexity:medium --color fbca04 --description "Medium complexity change"
   gh label create complexity:high --color cb2431 --description "High complexity change"
   gh label create status:ready-to-merge --color 6f42c1
   gh label create status:needs-senior-review --color 6f42c1
   gh label create high-priority --color ff7043
   gh label create large-changeset --color ff7043
   gh label create many-files --color ff7043
   gh label create merge-conflict-risk --color ff7043
   gh label create docs-needed --color ff7043
   ```

2. **Test with a Real PR**
   - Create a test PR in FreePanel with known metrics
   - Monitor Zapier task history for any failures
   - Verify labels and comments appear

3. **Customize Prompts for Your Team**
   - Adjust risk thresholds (e.g., "high" if > 40 files instead of 20)
   - Add domain-specific guidance (e.g., "Security-critical paths require extra scrutiny")
   - Include team standards in system prompt

4. **Set Up Monitoring**
   - Track Zap execution times and API costs
   - Log failures to a Slack channel for visibility
   - Review complexity classifications monthly for bias

---

# Advanced: Direct GitHub API Integration (Alternative to Zapier Actions)

If you need more control than Zapier's GitHub action, use the API code step:

```javascript
// Advanced: Direct GitHub API for label assignment
var token = inputData.github_token;
var repo = inputData.repo; // "JSXSTEWART/FreePanel"
var pr_number = inputData.pr_number;
var labels = inputData.labels;

var [owner, repo_name] = repo.split("/");

var url = "https://api.github.com/repos/" + owner + "/" + repo_name + 
          "/issues/" + pr_number + "/labels";

var response = fetch(url, {
  method: "POST",
  headers: {
    "Authorization": "token " + token,
    "Content-Type": "application/json"
  },
  body: JSON.stringify({ labels: labels })
});

var data = JSON.parse(response.text());
output = { success: true, applied_labels: data.length };
```

---

# Additional Resources

- [Zapier Code Step Documentation](https://zapier.com/help/doc/code)
- [OpenAI API Reference](https://platform.openai.com/docs/api-reference)
- [GitHub REST API](https://docs.github.com/en/rest)
- [GitHub Webhook Events](https://docs.github.com/en/developers/webhooks-and-events/webhooks/webhook-events-and-payloads)
- [Markdown in GitHub Comments](https://docs.github.com/en/get-started/writing-on-github/working-with-advanced-formatting)

---

**Last Updated:** December 20, 2025  
**Version:** 2.0 (High-Performance Edition)  
**Maintainer:** FreePanel Team
