# Structured Engineering Prompts: Zapier AI Code Step Quick Reference

A practical cheat sheet for building high-performance Zapier Code Steps with strict input validation, output contracts, and error handling.

---

## Table of Contents

1. [The 5 Core Principles](#the-5-core-principles)
2. [Template: Basic Normalization](#template-basic-normalization)
3. [Template: API Integration with Fallback](#template-api-integration-with-fallback)
4. [Template: Complex Parsing (Chain-of-Thought)](#template-complex-parsing-chain-of-thought)
5. [Common Mistakes & Fixes](#common-mistakes--fixes)
6. [Performance Optimization](#performance-optimization)
7. [Testing Checklist](#testing-checklist)

---

## The 5 Core Principles

### 1. Explicit InputData Declarations

**Bad:**
```
"Use the email to validate it."
```

**Good:**
```
"The email is available in inputData.email (string). 
The country code is in inputData.country_code (string, default "US").
If inputData.country_code is missing, use "US" as fallback."
```

**Why:** Prevents the AI from hallucinating variables that don't exist in `inputData`.

---

### 2. Strict Output Contract

**Bad:**
```
"Return the result."
```

**Good:**
```
"Assign the result to the 'output' variable as a JavaScript object:
{
  "success": true,
  "email_normalized": "john@gmail.com",
  "is_valid": true
}

If validation fails, return:
{
  "success": false,
  "error": "Email format invalid",
  "email_normalized": null
}

Do NOT throw an error. Always return an object."
```

**Why:** Zapier Code Steps require exact return format. String/array returns crash the step.

---

### 3. Defensive Coding (Try/Catch)

**Bad:**
```
var result = JSON.parse(inputData.payload);
```

**Good:**
```
try {
  var result = JSON.parse(inputData.payload);
} catch (e) {
  return {
    success: false,
    error: "JSON parse failed: " + e.message
  };
}
```

**Why:** Production data is messy. Graceful error handling prevents workflow crashes.

---

### 4. Null Checks & Defaults

**Bad:**
```
var name = inputData.name.toUpperCase();
```

**Good:**
```
var name = (inputData.name || "").trim();
if (!name) {
  return {
    success: false,
    error: "Name is required"
  };
}
var nameUpper = name.toUpperCase();
```

**Why:** Avoids "Cannot read property 'toUpperCase' of undefined" errors.

---

### 5. Chain-of-Thought for Complex Logic

**Bad:**
```
"Parse the CSV and extract line items."
```

**Good:**
```
"Parse the CSV by following this plan:

COMMENT PLAN:
// 1. Split the input by newline to get rows
// 2. For each row, split by comma to get fields
// 3. Handle quoted strings that contain commas
// 4. Trim whitespace from each field
// 5. Validate required fields (ID, Name, Price)
// 6. Convert numbers to proper types
// 7. Return array of objects

Then implement the plan as code."
```

**Why:** Forces the AI to reason through edge cases before coding.

---

## Template: Basic Normalization

Use this template for email, phone, company names, or other field cleaning.

```
Role: You are a Senior Backend Engineer specializing in data validation.

Task: Normalize and validate {FIELD_NAME}.

Input Mapping (Strict Mode):
The following variables are in inputData:
- inputData.{field_name} (string, raw input from user)
- inputData.{validation_option} (boolean, optional behavior flag)

Validation Plan (COMMENT APPROACH):
// 1. Trim whitespace
// 2. Convert to standard format (lowercase, title case, etc.)
// 3. Remove invalid characters
// 4. Check against validation rules
// 5. Return normalized value and validation status

Implementation:

var value = (inputData.{field_name} || "").trim();

if (!value) {
  return {
    success: false,
    error: "{FIELD_NAME} is required",
    {field_name}_normalized: null,
    is_valid: false
  };
}

// Normalize: [INSERT NORMALIZATION LOGIC]
var normalized = value.toLowerCase(); // Example

// Validate: [INSERT VALIDATION LOGIC]
var isValid = /^[a-z0-9]+$/.test(normalized); // Example

Output Contract (REQUIRED):
Assign to 'output' an object with this exact structure:
{
  "success": true,
  "{field_name}_normalized": "normalized-value",
  "{field_name}_original": "Original Value",
  "is_valid": true,
  "validation_notes": "Passed all checks"
}

If validation fails:
{
  "success": false,
  "error": "Validation error message",
  "{field_name}_normalized": null,
  "is_valid": false
}

Do NOT throw an error. Always return an object with success and is_valid fields.
```

---

## Template: API Integration with Fallback

Use this for OpenAI, HubSpot, Stripe, or any external API.

```
Role: You are a Senior Backend Engineer building production API integrations.

Task: Query {API_NAME} with fallback logic for rate limits and errors.

Input Mapping (Strict Mode):
The following variables are in inputData:
- inputData.api_key (string, your API key)
- inputData.query_field (string, what to search for)
- inputData.timeout_seconds (number, default 10)

Defensive Requirements:
1. Check that inputData.api_key is not empty. If missing, return error.
2. Implement try/catch around fetch() call.
3. Handle specific status codes:
   - 429 (rate limit): return fallback result instead of failing
   - 401/403 (auth): return { success: false, error: "Invalid API key" }
   - 500+ (server): retry up to 3 times with exponential backoff
4. Do NOT throw errors. Always return an object.

Implementation (Plan):

// COMMENT PLAN:
// 1. Validate API key and required inputs
// 2. Build request with proper headers and body
// 3. Call fetch with timeout handling
// 4. Parse JSON response
// 5. Extract relevant data from API response
// 6. Handle rate limits by returning fallback/cached value
// 7. Return result with success flag

var apiKey = inputData.api_key;
if (!apiKey) {
  return {
    success: false,
    error: "Missing API key"
  };
}

var url = "https://api.example.com/v1/search";
var headers = {
  "Authorization": "Bearer " + apiKey,
  "Content-Type": "application/json"
};
var body = JSON.stringify({
  query: inputData.query_field
});

try {
  var response = fetch(url, {
    method: "POST",
    headers: headers,
    body: body,
    timeout: (inputData.timeout_seconds || 10) * 1000
  });
  
  // Handle rate limit
  if (response.status === 429) {
    return {
      success: true,
      api_used: "fallback",
      result: null,
      message: "Rate limited - fallback used"
    };
  }
  
  // Handle auth errors
  if (response.status === 401 || response.status === 403) {
    return {
      success: false,
      error: "Unauthorized: Invalid API key",
      status: response.status
    };
  }
  
  // Handle server errors with retry
  if (response.status >= 500) {
    // Implement simple retry (single attempt shown here)
    // In production, add loop for 3 retries
    return {
      success: false,
      error: "API server error",
      status: response.status
    };
  }
  
  // Success: parse response
  if (response.ok) {
    var data = JSON.parse(response.text());
    return {
      success: true,
      api_used: "live",
      result: data,
      status: 200
    };
  }
  
} catch (e) {
  // Network error or timeout
  return {
    success: false,
    api_used: "fallback",
    error: "Network error: " + e.message
  };
}

Output Contract (REQUIRED):
{
  "success": true,
  "api_used": "live|fallback",
  "result": { ...API response data... },
  "status": 200
}

On error:
{
  "success": false,
  "error": "Error message",
  "api_used": "fallback",
  "status": 429|401|500
}
```

---

## Template: Complex Parsing (Chain-of-Thought)

Use this for CSV parsing, address extraction, or multi-field logic.

```
Role: You are a Senior Data Engineer specializing in complex parsing.

Task: Parse {DATA_FORMAT} and extract structured fields.

Input Mapping (Strict Mode):
The following variables are in inputData:
- inputData.raw_data (string, unparsed input)
- inputData.delimiter (string, default ",")
- inputData.strict_mode (boolean, fail on malformed rows)

Parsing Plan (COMMENT APPROACH - REQUIRED):
// 1. [Describe the parsing algorithm in comments]
// 2. [Explain how you'll handle edge cases]
// 3. [List validation checks]
// 4. [Show expected output structure]
// Then implement the plan as code

Implementation Example (CSV Parser):

// COMMENT PLAN:
// 1. Split input by newline to get rows
// 2. Skip empty rows
// 3. For first row, split by delimiter to get headers
// 4. For each data row:
//    a. Split by delimiter (respecting quoted strings)
//    b. Map values to header keys
//    c. Validate required fields
//    d. Convert data types (string to number, etc.)
// 5. Accumulate valid rows, track errors
// 6. Return array of parsed objects

var rawData = inputData.raw_data || "";
if (!rawData.trim()) {
  return {
    success: false,
    error: "Input data is empty",
    rows: []
  };
}

var lines = rawData.split(/\n/).filter(function(line) {
  return line.trim() !== "";
});

if (lines.length < 1) {
  return {
    success: false,
    error: "No data rows found",
    rows: []
  };
}

// Parse headers
var headerLine = lines[0];
var headers = headerLine.split(inputData.delimiter || ",");
headers = headers.map(function(h) { return h.trim(); });

var rows = [];
var errors = [];

// Parse data rows
for (var i = 1; i < lines.length; i++) {
  var line = lines[i];
  var values = line.split(inputData.delimiter || ",");
  
  // Check row length
  if (values.length !== headers.length) {
    var msg = "Row " + (i + 1) + ": expected " + headers.length + 
              " fields, got " + values.length;
    if (inputData.strict_mode) {
      return {
        success: false,
        error: msg,
        rows: rows,
        error_details: errors
      };
    }
    errors.push(msg);
    continue; // Skip this row
  }
  
  // Map values to headers
  var rowObj = {};
  for (var j = 0; j < headers.length; j++) {
    rowObj[headers[j]] = values[j].trim();
  }
  
  // Validate required fields
  if (!rowObj.id || !rowObj.name) {
    errors.push("Row " + (i + 1) + ": missing id or name");
    continue;
  }
  
  // Convert data types
  rowObj.id = parseInt(rowObj.id, 10);
  if (isNaN(rowObj.id)) {
    errors.push("Row " + (i + 1) + ": id must be numeric");
    continue;
  }
  
  rows.push(rowObj);
}

Output Contract (REQUIRED):
{
  "success": true,
  "row_count": 5,
  "rows": [
    { "id": 1, "name": "Item A", ... },
    { "id": 2, "name": "Item B", ... }
  ],
  "error_count": 0,
  "error_details": []
}

If strict mode and error:
{
  "success": false,
  "error": "Row 5: expected 3 fields, got 2",
  "row_count": 4,
  "rows": [ ... valid rows so far ... ],
  "error_details": [ ... list of errors ... ]
}
```

---

## Common Mistakes & Fixes

### Mistake 1: Forgetting Input Validation

**Before:**
```javascript
var email = inputData.email.toLowerCase();
```

**After:**
```javascript
var email = (inputData.email || "").trim().toLowerCase();
if (!email) {
  return { success: false, error: "Email is required" };
}
```

---

### Mistake 2: Throwing Errors Instead of Returning Objects

**Before:**
```javascript
try {
  var parsed = JSON.parse(inputData.json);
} catch (e) {
  throw new Error("Invalid JSON");
}
```

**After:**
```javascript
try {
  var parsed = JSON.parse(inputData.json);
} catch (e) {
  return {
    success: false,
    error: "Invalid JSON: " + e.message
  };
}
```

---

### Mistake 3: Returning Wrong Data Type

**Before:**
```javascript
output = "success"; // String, not object
```

**After:**
```javascript
output = {
  success: true,
  message: "Operation completed"
};
```

---

### Mistake 4: Not Handling Missing Variables in Fallback

**Before:**
```javascript
var country = inputData.country_code; // What if it's missing?
var formatted = "+" + country.substring(0, 2); // Crash!
```

**After:**
```javascript
var country = (inputData.country_code || "US").substring(0, 2);
var formatted = "+" + country;
```

---

### Mistake 5: Assuming Field Exists in Nested Object

**Before:**
```javascript
var phone = inputData.contact.phone; // What if contact is null?
```

**After:**
```javascript
var contact = inputData.contact || {};
var phone = (contact.phone || "").trim();
```

---

## Performance Optimization

### Timeout Handling

Zapier Code Steps have a **1-10 second timeout** depending on your plan. Respect this:

```javascript
// ✅ GOOD: Process only what's needed
var response = fetch(url, {
  method: "POST",
  timeout: 3000 // 3 seconds max
});

// ❌ BAD: No timeout, could hang forever
var response = fetch(url, { method: "POST" });
```

### Memory Efficiency

Keep in mind ~128MB memory limit:

```javascript
// ✅ GOOD: Stream process, don't load all at once
for (var i = 0; i < lines.length; i++) {
  processLine(lines[i]);
}

// ❌ BAD: Load everything into memory
var allData = [];
lines.forEach(function(line) {
  allData.push(parseHeavyObject(line));
});
```

### Avoid Nested Loops

```javascript
// ✅ GOOD: O(n log n)
var map = {};
items.forEach(function(item) {
  map[item.id] = item;
});
var matches = search_items.filter(function(s) {
  return map[s.id];
});

// ❌ BAD: O(n²)
search_items.forEach(function(s) {
  items.forEach(function(item) {
    if (s.id === item.id) { /* match */ }
  });
});
```

---

## Testing Checklist

Before deploying a Zapier workflow, test each Code Step:

- [ ] **Valid Input**: Step processes without error with correct data
- [ ] **Missing Field**: If a required field is null/undefined, returns error object (doesn't crash)
- [ ] **Empty String**: Handles empty string input gracefully
- [ ] **Wrong Data Type**: If API expects string but gets number, converts or errors gracefully
- [ ] **Timeout**: API call returns fallback if it takes >5 seconds
- [ ] **Output Format**: All outputs are objects (never strings/arrays)
- [ ] **Error Cases**: Returns `{ success: false, error: "..." }` (never throws)
- [ ] **Performance**: Full test completes in <5 seconds
- [ ] **Logging**: Can identify which step failed if workflow errors

### Test Template

```bash
# Create test input JSON
cat > test_input.json <<'EOF'
{
  "email": "test@example.com",
  "country_code": "US"
}
EOF

# Manually copy the code from your Zapier Code Step
# Wrap it to simulate inputData:
cat > test.js <<'EOF'
var inputData = JSON.parse(require('fs').readFileSync('test_input.json'));

// [Paste your code step logic here]

console.log(JSON.stringify(output, null, 2));
EOF

# Run in Node.js (or paste into Zapier's test runner)
node test.js
```

---

## Quick Prompt Snippets

### Email Validation Snippet
```
"Validate email using regex: /^[^\s@]+@[^\s@]+\.[^\s@]+$/
If valid, normalize to lowercase and remove spaces.
If invalid, return is_valid: false with reason."
```

### Phone US Standardization Snippet
```
"Extract digits only from phone.
If length is 10, assume it's a US number and format as (NPA) NXX-XXXX.
If length is 11 and starts with 1, remove the 1 and format.
Otherwise, return is_valid: false."
```

### Company Deduplication Snippet
```
"Compare inputData.company_raw against known_companies array using case-insensitive substring match.
If found, return company_id.
If not found and company starts with COMMON_SUFFIX (Inc, Corp, Ltd), try again with suffix removed."
```

### CSV Row Parsing Snippet
```
"Split by comma, but respect quoted strings.
If a field is quoted, remove quotes and handle escaped quotes.
Example: 'John \"Doc\" Doe' should parse as one field: John "Doc" Doe"
```

---

## Advanced: Exponential Backoff Retry

For API calls that might fail transiently:

```javascript
function fetchWithRetry(url, options, maxAttempts) {
  var attempt = 0;
  
  while (attempt < maxAttempts) {
    try {
      var response = fetch(url, options);
      if (response.ok || response.status < 500) {
        return response; // Success or client error, don't retry
      }
    } catch (e) {
      // Network error, might retry
    }
    
    attempt++;
    if (attempt < maxAttempts) {
      // Exponential backoff: 100ms, 200ms, 400ms, etc.
      var delayMs = Math.pow(2, attempt - 1) * 100;
      // Note: Zapier Code doesn't have sleep, so this is advisory
    }
  }
  
  return null;
}
```

---

## Resources

- [Zapier Code Step Docs](https://zapier.com/help/doc/code)
- [JavaScript Fetch API](https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API)
- [JSON Schema (for output validation)](https://json-schema.org/)
- [Regex Cheat Sheet](https://www.regular-expressions.info/cheatsheet.html)

---

**Version:** 1.0  
**Last Updated:** December 20, 2025  
**Author:** FreePanel Zapier Integration Team
