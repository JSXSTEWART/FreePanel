# Zapier Workflow: Lead Normalization Engine
## High-Performance Edition with Structured Engineering Prompts

This workflow transforms raw, messy lead data from multiple sources into clean, standardized records suitable for CRM insertion (HubSpot, Salesforce, Pipedrive).

---

## Executive Summary

**The Problem:** Raw lead data is inconsistent:
- Email: "John.Doe@Gmail.com" vs "john.doe@gmail.com"
- Phone: "555-123-4567" vs "+1 (555) 123-4567" vs "5551234567"
- Name: "JOHN DOE" vs "John Doe" vs "john doe" vs "Doe, John"
- Address: "123 Main St Apt B, Springfield IL 62701" vs "123 Main Street Apt B Springfield Illinois 62701"
- Company: "Acme Corp" vs "ACME CORPORATION" vs "Acme, Inc."

**The Solution:** Structured normalization with:
1. **Email canonicalization** — lowercase, domain normalization, validation
2. **Phone standardization** — extract digits, apply country format (US +1-NPA-NXX-XXXX)
3. **Name parsing** — detect "Lastname, Firstname" vs "Firstname Lastname", title extraction
4. **Address standardization** — zip code parsing, state abbreviation, optional geocoding
5. **Company deduplication** — fuzzy matching against known records, acronym expansion
6. **Duplicate detection** — check if lead already exists in CRM before insert

---

## Architecture Overview

```
Form Submission / CSV Import / Email / API Webhook
     ↓
[STEP 1] Normalize Email & Validate (Code Step)
     ↓ inputData: {email, allow_disposable}
     ↓ output: {email_normalized, is_valid, domain_risk}
     ↓
[STEP 2] Parse & Standardize Name (Code Step)
     ↓ inputData: {first_name, last_name, full_name}
     ↓ output: {first_name, last_name, salutation, title}
     ↓
[STEP 3] Standardize Phone Number (Code Step)
     ↓ inputData: {phone, country_code}
     ↓ output: {phone_normalized, phone_formatted, is_valid}
     ↓
[STEP 4] Parse & Normalize Address (Code Step)
     ↓ inputData: {address_line_1, address_line_2, city, state, zip}
     ↓ output: {address_std, city, state, zip, country}
     ↓
[STEP 5] AI-Powered Company Normalization (Code Step + OpenAI)
     ↓ inputData: {company_raw, industry, openai_key}
     ↓ output: {company_normalized, domain_inferred, industry_category}
     ↓
[STEP 6] Check for Duplicates (Code Step + CRM/API)
     ↓ inputData: {email_normalized, crm_key, crm_type}
     ↓ output: {is_duplicate, existing_id, merge_score}
     ↓
[DECISION] If duplicate found:
     ├─→ [BRANCH A] Update Existing Lead (CRM Action)
     └─→ [BRANCH B] Create New Lead (CRM Action)
     ↓
[OPTIONAL] Send Verification Email (Email Action)
```

---

# Detailed Implementation

## Step 1: Normalize Email & Validate

### Purpose
Standardize email addresses while detecting spam/disposable domains.

### Zapier Setup
1. Create **Code by Zapier** step (JavaScript)
2. Map input:
   ```
   email → (from form/webhook)
   allow_disposable → (default: false)
   skip_mx_check → (default: true, avoid API delays)
   ```

### High-Performance Prompt

```
Role: You are a Senior Backend Engineer specializing in email validation and normalization.

Task: Normalize and validate email addresses with disposable domain detection.

Input Mapping (Strict Mode):
The following variables are in inputData:
- inputData.email (string, raw email from user input)
- inputData.allow_disposable (boolean, allow temp services like temp-mail.org)
- inputData.skip_mx_check (boolean, skip DNS MX record lookup for speed)

Validation & Normalization Plan (COMMENT APPROACH):
// 1. Trim whitespace and detect common typos (e.g., "gmai.com" → "gmail.com")
// 2. Convert to lowercase
// 3. Extract local part (before @) and domain part (after @)
// 4. Check for common disposable domains
// 5. Apply domain-specific normalization (Gmail, Outlook, etc.)
// 6. Validate format with regex
// 7. Return normalized email and metadata

Implementation:

// Trim and lowercase
var email = (inputData.email || "").trim().toLowerCase();

// Basic format validation
var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
if (!email || !emailRegex.test(email)) {
  return {
    success: false,
    error: "Invalid email format",
    email_normalized: null,
    is_valid: false
  };
}

// Split into local and domain
var parts = email.split("@");
var localPart = parts[0];
var domain = parts[1];

// Common disposable domain list (top 100)
var disposableDomains = [
  "temp-mail.org", "tempmail.com", "guerrillamail.com", "mailinator.com",
  "10minutemail.com", "throwaway.email", "yopmail.com", "mailnesia.com",
  "trashmail.com", "fakeinbox.com", "maildrop.net", "spam4.me",
  "e4ward.com", "mytrashmail.com", "temp-mails.com", "fakeemail.net"
  // ... extend this list as needed
];

var isDomainDisposable = disposableDomains.indexOf(domain) !== -1;
if (isDomainDisposable && !inputData.allow_disposable) {
  return {
    success: true,
    email_normalized: email,
    is_valid: false,
    reason: "disposable_domain",
    domain_risk: "high"
  };
}

// Gmail alias handling: gmail ignores dots, so normalize
if (domain === "gmail.com" || domain === "googlemail.com") {
  localPart = localPart.replace(/\./g, "");
  email = localPart + "@gmail.com";
  domain = "gmail.com";
}

// Outlook alias handling: Outlook ignores everything after +
if (domain.includes("outlook.") || domain.includes("hotmail.")) {
  var plusIndex = localPart.indexOf("+");
  if (plusIndex !== -1) {
    localPart = localPart.substring(0, plusIndex);
  }
}

// Classify domain risk
var domainRisk = "low";
if (domain.includes("temp") || domain.includes("trash")) domainRisk = "high";
if (domain.includes("test") || domain.includes("example")) domainRisk = "medium";

Output Contract (REQUIRED):
Assign to 'output' an object with this exact structure:
{
  "success": true,
  "email_normalized": "john.doe@gmail.com",
  "email_original": "John.Doe@Gmail.com",
  "local_part": "johndoe",
  "domain": "gmail.com",
  "is_valid": true,
  "domain_risk": "low",
  "is_disposable": false,
  "aliases_handled": ["gmail_dots_removed"]
}

If validation fails:
{
  "success": false,
  "error": "Invalid email format or disposable domain",
  "email_normalized": null,
  "is_valid": false,
  "domain_risk": "high|medium|low"
}

Do NOT throw an error. Always return an object with at least success and is_valid fields.
```

### Example Output
```json
{
  "success": true,
  "email_normalized": "johndoe@gmail.com",
  "email_original": "John.Doe@Gmail.com",
  "local_part": "johndoe",
  "domain": "gmail.com",
  "is_valid": true,
  "domain_risk": "low",
  "is_disposable": false,
  "aliases_handled": ["gmail_dots_removed"]
}
```

---

## Step 2: Parse & Standardize Name

### Purpose
Extract first/last name from various formats and detect salutations (Dr., Prof., etc.).

### Zapier Setup
1. Create **Code by Zapier** step
2. Map input:
   ```
   first_name → (optional)
   last_name → (optional)
   full_name → (optional, "Doe, John" or "John Doe")
   suffix → (optional, "Jr.", "Sr.", "PhD")
   ```

### High-Performance Prompt

```
Role: You are a Senior Data Engineer specializing in name parsing.

Task: Parse and standardize person names from various formats.

Input Mapping (Strict Mode):
The following variables are in inputData:
- inputData.first_name (string, may be empty)
- inputData.last_name (string, may be empty)
- inputData.full_name (string, may be empty, format: "First Last" or "Last, First")
- inputData.suffix (string, may be empty, e.g., "Jr.", "Ph.D")

Parsing Plan (COMMENT APPROACH):
// 1. Determine which format we have: (first, last) vs (full_name)
// 2. If full_name provided, detect if "Lastname, Firstname" (comma present) or "Firstname Lastname"
// 3. Extract salutation/title (Dr., Prof., Mr., Ms., etc.) from beginning
// 4. Extract suffix (Jr., Sr., Ph.D, III, etc.) from end
// 5. Capitalize each word appropriately
// 6. Return standardized first, last, salutation, and suffix separately

Implementation:

var first_name = (inputData.first_name || "").trim();
var last_name = (inputData.last_name || "").trim();
var full_name = (inputData.full_name || "").trim();
var suffix = (inputData.suffix || "").trim();

// Salutation patterns
var salutations = ["Dr.", "Mr.", "Ms.", "Mrs.", "Prof.", "Rev.", "Hon.", "Sir", "Madam"];
var suffixes = ["Jr.", "Sr.", "II", "III", "IV", "V", "Ph.D", "M.D", "Esq."];

var extractedSalutation = "";
var extractedSuffix = suffix;
var firstName = first_name;
var lastName = last_name;

// Case 1: Both first and last provided - easy path
if (firstName && lastName) {
  // Check for salutation in first_name
  salutations.forEach(function(sal) {
    if (firstName.toLowerCase().startsWith(sal.toLowerCase())) {
      extractedSalutation = sal;
      firstName = firstName.substring(sal.length).trim();
    }
  });
} else if (full_name) {
  // Case 2: Only full_name provided - parse it
  
  // Check for salutation at start
  salutations.forEach(function(sal) {
    if (full_name.toLowerCase().startsWith(sal.toLowerCase())) {
      extractedSalutation = sal;
      full_name = full_name.substring(sal.length).trim();
    }
  });
  
  // Check for suffix at end
  suffixes.forEach(function(suf) {
    if (full_name.toLowerCase().endsWith(suf.toLowerCase())) {
      extractedSuffix = suf;
      full_name = full_name.substring(0, full_name.length - suf.length).trim();
    }
  });
  
  // Detect format: "Last, First" vs "First Last"
  if (full_name.indexOf(",") !== -1) {
    // Format: "Last, First"
    var parts = full_name.split(",");
    lastName = parts[0].trim();
    firstName = parts[1].trim();
  } else {
    // Format: "First Last" - assume last word is last name
    var words = full_name.trim().split(/\s+/);
    if (words.length === 1) {
      // Only one word - assume first name
      firstName = words[0];
      lastName = "";
    } else if (words.length === 2) {
      firstName = words[0];
      lastName = words[1];
    } else {
      // Multiple words: first word is first name, rest is last name
      firstName = words[0];
      lastName = words.slice(1).join(" ");
    }
  }
}

// Capitalize properly (Title Case)
function titleCase(str) {
  if (!str) return "";
  return str.split(/\s+/).map(function(word) {
    return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
  }).join(" ");
}

firstName = titleCase(firstName);
lastName = titleCase(lastName);

Output Contract (REQUIRED):
Assign to 'output' an object with this exact structure:
{
  "success": true,
  "first_name": "John",
  "last_name": "Doe",
  "full_name": "John Doe",
  "salutation": "Mr.",
  "suffix": "Jr.",
  "display_name": "John Doe"
}

Error Handling:
If name cannot be parsed:
{
  "success": false,
  "error": "Unable to parse name from provided input",
  "first_name": null,
  "last_name": null,
  "full_name": "Unparseable Input"
}

Do NOT throw an error. Return object with at least success and names fields.
```

### Example Output
```json
{
  "success": true,
  "first_name": "John",
  "last_name": "Doe",
  "full_name": "John Doe",
  "salutation": "Mr.",
  "suffix": "Jr.",
  "display_name": "John Doe, Jr."
}
```

---

## Step 3: Standardize Phone Number

### Purpose
Extract digits, validate format, and apply country-specific formatting.

### Zapier Setup
1. Create **Code by Zapier** step
2. Map input:
   ```
   phone → (raw phone from form)
   country_code → (default: "US", optional)
   ```

### High-Performance Prompt

```
Role: You are a Phone Data Engineer.

Task: Standardize and validate phone numbers with country-specific formatting.

Input Mapping (Strict Mode):
The following variables are in inputData:
- inputData.phone (string, raw phone input, may include formatting)
- inputData.country_code (string, 2-letter ISO code, default "US")

Phone Formats Supported:
- US: +1-NPA-NXX-XXXX or (NPA) NXX-XXXX or 1NPA-NXX-XXXX or NPA NXX XXXX
- Canada: Same as US (country code +1)
- UK: +44 or 0, then up to 11 digits
- International: +CC NNNN...

Parsing Plan (COMMENT APPROACH):
// 1. Remove common formatting: spaces, parentheses, dashes, dots
// 2. Extract country code if present (leading +)
// 3. Validate digit count for country
// 4. Format according to country standard
// 5. Check if number looks like valid range (not all 0s, sequential, etc.)

Implementation (US Example):

var phone = (inputData.phone || "").trim();
var countryCode = (inputData.country_code || "US").toUpperCase();

if (!phone) {
  return {
    success: false,
    error: "Phone number is empty",
    phone_normalized: null
  };
}

// Remove formatting characters
var digits = phone.replace(/[^\d+]/g, "");

// Extract country code if present
var extractedCC = "1"; // Default to US
if (digits.startsWith("+")) {
  digits = digits.substring(1);
  var ccMatch = digits.match(/^(\d{1,3})/);
  if (ccMatch) {
    extractedCC = ccMatch[1];
    digits = digits.substring(ccMatch[0].length);
  }
}

// Handle US/Canada leading 1 that's not +
if ((countryCode === "US" || countryCode === "CA") && digits.startsWith("1")) {
  digits = digits.substring(1);
}

// Validate length for US/Canada (should be 10 digits after cleanup)
if ((countryCode === "US" || countryCode === "CA")) {
  if (digits.length !== 10) {
    return {
      success: false,
      error: "US phone must be 10 digits (got " + digits.length + ")",
      phone_normalized: null,
      is_valid: false
    };
  }
  
  // Check for obviously invalid patterns (all same digit, sequential, etc.)
  var uniqueDigits = new Set(digits.split("")).size;
  if (uniqueDigits === 1) {
    return {
      success: false,
      error: "Phone has all identical digits",
      phone_normalized: null,
      is_valid: false
    };
  }
  
  // Format: +1-NPA-NXX-XXXX
  var npa = digits.substring(0, 3);
  var nxx = digits.substring(3, 6);
  var xxxx = digits.substring(6, 10);
  
  var phoneNormalized = "+1-" + npa + "-" + nxx + "-" + xxxx;
  var phoneFormatted = "(" + npa + ") " + nxx + "-" + xxxx;
  var phonePlain = "+1" + digits;
}

Output Contract (REQUIRED):
Assign to 'output' an object with this exact structure:
{
  "success": true,
  "phone_normalized": "+1-555-123-4567",
  "phone_formatted": "(555) 123-4567",
  "phone_plain": "+15551234567",
  "country_code": "US",
  "area_code": "555",
  "is_valid": true,
  "validation_notes": "Valid US phone number"
}

If validation fails:
{
  "success": false,
  "error": "Invalid phone format",
  "phone_normalized": null,
  "is_valid": false,
  "country_code": "US"
}

Do NOT throw an error. Always return object with success and is_valid fields.
```

### Example Output
```json
{
  "success": true,
  "phone_normalized": "+1-555-123-4567",
  "phone_formatted": "(555) 123-4567",
  "phone_plain": "+15551234567",
  "country_code": "US",
  "area_code": "555",
  "is_valid": true,
  "validation_notes": "Valid US phone number"
}
```

---

## Step 4: Parse & Normalize Address

### Purpose
Standardize address format and extract components (street, city, state, zip).

### Zapier Setup
1. Create **Code by Zapier** step
2. Map input:
   ```
   address_line_1 → (street address)
   address_line_2 → (apt, suite, optional)
   city → (optional)
   state → (optional, 2-letter or full name)
   zip → (optional, 5 or 9 digits)
   country → (optional, default "US")
   ```

### High-Performance Prompt

```
Role: You are a Senior Address Data Engineer.

Task: Parse and normalize addresses with state abbreviation and zip code validation.

Input Mapping (Strict Mode):
The following variables are in inputData:
- inputData.address_line_1 (string, street address)
- inputData.address_line_2 (string, apartment/suite, may be empty)
- inputData.city (string, may be empty)
- inputData.state (string, 2-letter code or full name, may be empty)
- inputData.zip (string, 5 or 9 digit format, may be empty)
- inputData.country (string, default "US")

State Abbreviation Map:
Create a map: "Alabama" → "AL", "Alaska" → "AK", ... "Wyoming" → "WY"

Address Parsing Plan (COMMENT APPROACH):
// 1. Normalize address_line_1 (remove extra whitespace, standardize abbreviations)
// 2. Validate state code (convert full name to 2-letter if needed)
// 3. Validate zip code format (5 digits or 5+4 format)
// 4. Handle rare cases: P.O. boxes, rural routes
// 5. Return standardized address components

Implementation:

var address_line_1 = (inputData.address_line_1 || "").trim();
var address_line_2 = (inputData.address_line_2 || "").trim();
var city = (inputData.city || "").trim();
var state = (inputData.state || "").trim();
var zip = (inputData.zip || "").trim();

// State mapping (full name → 2-letter code)
var stateMap = {
  "alabama": "AL", "alaska": "AK", "arizona": "AZ", "arkansas": "AR",
  "california": "CA", "colorado": "CO", "connecticut": "CT", "delaware": "DE",
  "florida": "FL", "georgia": "GA", "hawaii": "HI", "idaho": "ID",
  "illinois": "IL", "indiana": "IN", "iowa": "IA", "kansas": "KS",
  "kentucky": "KY", "louisiana": "LA", "maine": "ME", "maryland": "MD",
  "massachusetts": "MA", "michigan": "MI", "minnesota": "MN", "mississippi": "MS",
  "missouri": "MO", "montana": "MT", "nebraska": "NE", "nevada": "NV",
  "new hampshire": "NH", "new jersey": "NJ", "new mexico": "NM", "new york": "NY",
  "north carolina": "NC", "north dakota": "ND", "ohio": "OH", "oklahoma": "OK",
  "oregon": "OR", "pennsylvania": "PA", "rhode island": "RI", "south carolina": "SC",
  "south dakota": "SD", "tennessee": "TN", "texas": "TX", "utah": "UT",
  "vermont": "VT", "virginia": "VA", "washington": "WA", "west virginia": "WV",
  "wisconsin": "WI", "wyoming": "WY", "district of columbia": "DC"
};

// Convert state to 2-letter code
if (state && state.length > 2) {
  var stateLower = state.toLowerCase();
  state = stateMap[stateLower] || state.substring(0, 2).toUpperCase();
} else if (state) {
  state = state.toUpperCase();
}

// Validate zip code
var zipValid = /^\d{5}(-\d{4})?$/.test(zip);
if (!zipValid && zip) {
  // Try to extract just digits
  var zipDigits = zip.replace(/\D/g, "");
  if (zipDigits.length === 5) {
    zip = zipDigits;
    zipValid = true;
  } else if (zipDigits.length === 9) {
    zip = zipDigits.substring(0, 5) + "-" + zipDigits.substring(5);
    zipValid = true;
  } else {
    zipValid = false;
  }
}

// Normalize address_line_1: remove duplicate spaces, standardize abbreviations
address_line_1 = address_line_1.replace(/\s+/g, " ");
address_line_1 = address_line_1.replace(/\bSt\b/g, "Street");
address_line_1 = address_line_1.replace(/\bAve\b/g, "Avenue");
address_line_1 = address_line_1.replace(/\bBlvd\b/g, "Boulevard");
address_line_1 = address_line_1.replace(/\bDr\b/g, "Drive");
address_line_1 = address_line_1.replace(/\bLn\b/g, "Lane");
address_line_1 = address_line_1.replace(/\bPl\b/g, "Place");
address_line_1 = address_line_1.replace(/\bRd\b/g, "Road");
address_line_1 = address_line_1.replace(/\bCt\b/g, "Court");

// Title case city and state
city = city.split(" ").map(function(word) {
  return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
}).join(" ");

Output Contract (REQUIRED):
Assign to 'output' an object with this exact structure:
{
  "success": true,
  "address_line_1": "123 Main Street",
  "address_line_2": "Apt B",
  "city": "Springfield",
  "state": "IL",
  "zip": "62701",
  "zip_extended": null,
  "country": "US",
  "is_valid": true,
  "full_address": "123 Main Street, Apt B, Springfield, IL 62701"
}

If validation fails:
{
  "success": false,
  "error": "Invalid address components",
  "is_valid": false
}
```

### Example Output
```json
{
  "success": true,
  "address_line_1": "123 Main Street",
  "address_line_2": "Apt B",
  "city": "Springfield",
  "state": "IL",
  "zip": "62701",
  "zip_extended": null,
  "country": "US",
  "is_valid": true,
  "full_address": "123 Main Street, Apt B, Springfield, IL 62701"
}
```

---

## Step 5: AI-Powered Company Normalization

### Purpose
Use OpenAI to detect company variations and infer company domain/industry.

### Zapier Setup
1. Create **Code by Zapier** step
2. Map input:
   ```
   company_raw → (user input: "Acme Corp" vs "ACME CORPORATION")
   openai_api_key → (Zapier Env Variable)
   known_companies → (optional, JSON array of {name, domain, id})
   ```

### High-Performance Prompt

```
Role: You are a Sales Data Engineer specializing in company deduplication.

Task: Normalize company names and infer company details using AI.

Input Mapping (Strict Mode):
The following variables are in inputData:
- inputData.company_raw (string, messy company name from form)
- inputData.openai_api_key (string, your OpenAI API key)
- inputData.known_companies (array of objects, optional, format: [{name, domain, id}, ...])
- inputData.industry_hint (string, optional, e.g., "Healthcare")

Defensive Requirements:
1. If company_raw is empty or null, return success: false
2. If openai_api_key is missing, use fallback heuristic matching only
3. If known_companies is not an array, treat as empty []
4. Wrap API call in try/catch; on error, use heuristic fallback

Company Normalization Plan (COMMENT APPROACH):
// 1. Check if company exists in known_companies array (case-insensitive fuzzy match)
// 2. If found, return known details (domain, id)
// 3. If not found and OpenAI available, query AI for standardization
// 4. AI extracts: canonical company name, likely domain, industry, parent company if applicable
// 5. Return normalized details for CRM insertion

Implementation (Heuristic Fallback):

var companyRaw = (inputData.company_raw || "").trim();
if (!companyRaw) {
  return {
    success: false,
    error: "Company name is empty"
  };
}

var knownCompanies = inputData.known_companies || [];
var companyLower = companyRaw.toLowerCase();

// Check known companies for exact or fuzzy match
var foundKnown = null;
knownCompanies.forEach(function(known) {
  var knownNameLower = known.name.toLowerCase();
  // Exact match or contains (fuzzy)
  if (companyLower === knownNameLower || 
      companyLower.includes(knownNameLower.substring(0, 5))) {
    foundKnown = known;
  }
});

if (foundKnown) {
  return {
    success: true,
    company_normalized: foundKnown.name,
    company_domain: foundKnown.domain || "unknown",
    company_id: foundKnown.id || null,
    source: "known_companies_list"
  };
}

// OpenAI Fallback (if available)
if (inputData.openai_api_key) {
  var prompt = "Standardize this company name for a CRM database. " +
    "Return ONLY a JSON object with: { normalized: string, domain: string, industry: string }\n" +
    "Company: " + companyRaw;
  
  try {
    var response = fetch("https://api.openai.com/v1/chat/completions", {
      method: "POST",
      headers: {
        "Authorization": "Bearer " + inputData.openai_api_key,
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        model: "gpt-4-turbo",
        messages: [{role: "user", content: prompt}],
        max_tokens: 100,
        temperature: 0.3
      })
    });
    
    if (response.ok) {
      var data = JSON.parse(response.text());
      var aiResponse = data.choices[0].message.content;
      var aiData = JSON.parse(aiResponse);
      
      return {
        success: true,
        company_normalized: aiData.normalized,
        company_domain: aiData.domain,
        industry: aiData.industry,
        source: "openai"
      };
    }
  } catch (e) {
    // Fall through to heuristic-only result
  }
}

// Heuristic-only (if no API available)
var normalized = companyRaw.split(/\s+(Inc|Corp|Ltd|LLC|Co|Group|Holdings)/i)[0].trim();
normalized = normalized.split(/[,&]/)[0].trim();
normalized = normalized.replace(/[™®©]/g, "").trim();

Output Contract (REQUIRED):
Assign to 'output' an object with this exact structure:
{
  "success": true,
  "company_raw": "ACME Corp, Inc.",
  "company_normalized": "Acme Corporation",
  "company_domain": "acmecorp.com",
  "industry": "Manufacturing",
  "source": "openai|heuristic|known_companies_list",
  "confidence": 0.95
}

If fails:
{
  "success": false,
  "error": "Unable to normalize company",
  "company_normalized": null
}
```

### Example Output
```json
{
  "success": true,
  "company_raw": "ACME Corp, Inc.",
  "company_normalized": "Acme Corporation",
  "company_domain": "acmecorp.com",
  "industry": "Manufacturing",
  "source": "openai",
  "confidence": 0.95
}
```

---

## Step 6: Check for Duplicates (CRM/Database Lookup)

### Purpose
Query CRM (HubSpot, Salesforce) to determine if lead already exists.

### Zapier Setup
1. Create **Code by Zapier** step or native CRM action
2. Map input:
   ```
   email_normalized → (from Step 1)
   phone_normalized → (from Step 3)
   crm_type → ("hubspot", "salesforce", "pipedrive")
   crm_api_key → (Zapier Env Variable)
   ```

### High-Performance Prompt

```
Role: You are a Data Integration Engineer working with CRM APIs.

Task: Query CRM to check if lead already exists and calculate merge score.

Input Mapping (Strict Mode):
The following variables are in inputData:
- inputData.email_normalized (string, canonical email)
- inputData.phone_normalized (string, canonical phone)
- inputData.crm_type (string, "hubspot" | "salesforce" | "pipedrive")
- inputData.crm_api_key (string, your CRM API key)
- inputData.first_name (string, for additional matching)
- inputData.last_name (string, for additional matching)

Defensive Requirements:
1. If email_normalized is empty, skip email search
2. If phone_normalized is empty, skip phone search
3. Implement exponential backoff retry (3 attempts, 1s delay) for API calls
4. On API error, return { success: true, is_duplicate: false, error: "API unavailable" }
5. Always set a merge_score (0.0 to 1.0) indicating confidence that records are same person

Lookup Strategy (COMMENT APPROACH):
// 1. Prepare search queries for email and phone (separate)
// 2. Query CRM for each field
// 3. Combine results and rank by similarity
// 4. Calculate merge_score based on field matches:
//    - Email match: +0.8 (very high confidence)
//    - Phone match: +0.7
//    - First+Last name match: +0.5
// 5. Return top match and confidence score

Implementation (HubSpot Example):

var crm = inputData.crm_type || "hubspot";
var apiKey = inputData.crm_api_key;
var email = inputData.email_normalized;
var phone = inputData.phone_normalized;

if (!email && !phone) {
  return {
    success: false,
    error: "Must provide email or phone for lookup"
  };
}

var results = [];

if (email && crm === "hubspot") {
  // HubSpot: Search by email
  try {
    var url = "https://api.hubapi.com/crm/v3/objects/contacts/search";
    var response = fetch(url, {
      method: "POST",
      headers: {
        "Authorization": "Bearer " + apiKey,
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        filterGroups: [{
          filters: [{
            propertyName: "email",
            operator: "EQ",
            value: email
          }]
        }],
        limit: 1
      })
    });
    
    if (response.ok) {
      var data = JSON.parse(response.text());
      if (data.results && data.results.length > 0) {
        results.push({
          id: data.results[0].id,
          match_field: "email",
          confidence: 0.95,
          properties: data.results[0].properties
        });
      }
    }
  } catch (e) {
    // Continue with phone search
  }
}

if (phone && results.length === 0 && crm === "hubspot") {
  // HubSpot: Search by phone
  try {
    var url = "https://api.hubapi.com/crm/v3/objects/contacts/search";
    var response = fetch(url, {
      method: "POST",
      headers: {
        "Authorization": "Bearer " + apiKey,
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        filterGroups: [{
          filters: [{
            propertyName: "phone",
            operator: "EQ",
            value: phone
          }]
        }],
        limit: 1
      })
    });
    
    if (response.ok) {
      var data = JSON.parse(response.text());
      if (data.results && data.results.length > 0) {
        results.push({
          id: data.results[0].id,
          match_field: "phone",
          confidence: 0.85,
          properties: data.results[0].properties
        });
      }
    }
  } catch (e) {
    // API error handled below
  }
}

var isDuplicate = results.length > 0;
var mergeScore = isDuplicate ? results[0].confidence : 0.0;

Output Contract (REQUIRED):
Assign to 'output' an object with this exact structure:
{
  "success": true,
  "is_duplicate": false,
  "merge_score": 0.0,
  "existing_id": null,
  "match_field": null,
  "message": "New contact - ready to create"
}

If duplicate found:
{
  "success": true,
  "is_duplicate": true,
  "merge_score": 0.95,
  "existing_id": "503da580-62c3-11ec-81d7-0242ac130003",
  "match_field": "email",
  "message": "Duplicate found - recommend update instead of create"
}

If API unavailable:
{
  "success": true,
  "is_duplicate": false,
  "merge_score": 0.0,
  "api_error": "API timeout - proceeding with create",
  "recommendation": "Manual review recommended"
}
```

### Example Output
```json
{
  "success": true,
  "is_duplicate": true,
  "merge_score": 0.95,
  "existing_id": "503da580-62c3-11ec-81d7-0242ac130003",
  "match_field": "email",
  "message": "Duplicate found - recommend update instead of create"
}
```

---

## Step 7: Conditional Logic (If Duplicate)

### Zapier Setup
1. Add **Filter** step:
   ```
   Condition: If [Step 6] is_duplicate equals true
   Action: Continue to Step 8A (Update Existing)
   Else: Continue to Step 8B (Create New)
   ```

---

## Step 8A: Update Existing Lead (If Duplicate)

### Zapier Setup
1. Use **HubSpot** action: "Update Contact"
2. Configure:
   ```
   Contact ID: [Step 6] existing_id
   Email: [Step 1] email_normalized
   First Name: [Step 2] first_name
   Last Name: [Step 2] last_name
   Phone: [Step 3] phone_formatted
   Company: [Step 5] company_normalized
   City: [Step 4] city
   State: [Step 4] state
   Zip: [Step 4] zip
   Last Updated: Current Timestamp
   Last Update Source: "Zapier Lead Normalizer"
   ```

---

## Step 8B: Create New Lead (If Not Duplicate)

### Zapier Setup
1. Use **HubSpot** action: "Create Contact"
2. Configure:
   ```
   Email: [Step 1] email_normalized
   First Name: [Step 2] first_name
   Last Name: [Step 2] last_name
   Phone: [Step 3] phone_formatted
   Company: [Step 5] company_normalized
   Address: [Step 4] full_address
   City: [Step 4] city
   State: [Step 4] state
   Zip: [Step 4] zip
   Country: [Step 4] country
   Lead Source: "Web Form" (or dynamic from webhook)
   Lead Status: "New"
   Created Via: "Zapier Lead Normalizer"
   ```

---

## Step 9: Optional - Send Verification Email

### Zapier Setup
1. Add **Email** action (Gmail/Outlook/SendGrid)
2. Configure:
   ```
   To: [Step 1] email_normalized
   From: noreply@yourapp.com
   Subject: Confirm Your Information
   Body Template:
   ---
   Hi {{first_name}},
   
   We received your information. Please review and confirm:
   
   - Email: {{email_normalized}}
   - Phone: {{phone_formatted}}
   - Address: {{full_address}}
   
   [Confirm Details Button → link to verification page]
   
   Best regards,
   FreePanel Team
   ---
   ```

---

# Testing & Validation

## Test Cases

### Test 1: Perfect Input
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john.doe@gmail.com",
  "phone": "(555) 123-4567",
  "address_line_1": "123 Main St",
  "city": "Springfield",
  "state": "IL",
  "zip": "62701",
  "company": "Acme Corporation"
}
```

**Expected Output:** All steps succeed, no duplicate, create new lead.

### Test 2: Messy Data
```json
{
  "first_name": "JOHN",
  "last_name": "doe",
  "email": "John.Doe@GMAIL.COM",
  "phone": "555-123-4567",
  "address_line_1": "123 Main St., Apt. B",
  "city": "springfield",
  "state": "illinois",
  "zip": "62701",
  "company": "ACME, INC."
}
```

**Expected Output:**
- First name → "John"
- Last name → "Doe"
- Email → "johndoe@gmail.com" (Gmail dots removed)
- Phone → "+1-555-123-4567"
- State → "IL"
- Company → "Acme Incorporated"

### Test 3: Duplicate Detection
```json
{
  "email": "john.doe@gmail.com",
  "phone": "(555) 123-4567"
}
```

**Expected Output:** Step 6 returns `is_duplicate: true`, flow updates existing contact instead of creating.

### Test 4: Invalid Phone
```json
{
  "phone": "999999999999" // Invalid: too many digits
}
```

**Expected Output:** Step 3 returns `is_valid: false`, warning flag, but workflow continues (phone is optional).

### Test 5: Disposable Email
```json
{
  "email": "john.doe@tempmail.org",
  "allow_disposable": false
}
```

**Expected Output:** Step 1 returns `domain_risk: "high"`, `is_disposable: true`, optional warning flag.

---

# Configuration Examples

## Enterprise (GDPR Compliant)

```
Step 1: Stricter email validation, reject disposable domains
Step 2: Require first + last name (not just full_name)
Step 3: Require valid phone number
Step 4: Require complete address (no partial zips)
Step 6: Check for duplicates with high merge_score threshold (0.9+)
Step 9: Always send verification email
CRM Action: Mark all new contacts as "needs_verification" until confirmed
```

## Startup (Speed Focused)

```
Step 1–5: Normalize everything, but don't require strict validation
Step 6: Skip duplicate check (faster) or use email-only (not phone)
Step 8B: Create immediately, no verification
Step 9: Skip verification email
CRM Action: Import directly to "New Leads" list
```

## Real Estate (Address Critical)

```
Step 1–3: Standard normalization
Step 4: Enhanced address validation, geocoding optional
Step 5: Look up property records by address
Step 6: Check duplicate by address + name (not just email)
Step 8B: Enrich with property details (Zillow API, etc.)
Step 9: Send property recommendation email based on history
```

---

# Monitoring & Quality

## Key Metrics

- **Normalization Success Rate**: % of leads passing all 6 steps
- **Duplicate Detection Rate**: % of duplicates found (tune merge_score threshold)
- **Email Validity**: % with domain_risk = "low"
- **Phone Validation**: % with valid phone numbers
- **Company Normalization**: % successfully mapped to known companies

## Logging

Add logging step after each code step:

```javascript
output.step_name = "Step 1: Email Normalization";
output.timestamp = new Date().toISOString();
output.duration_ms = (Date.now() - inputData._start_time);

// Log to Slack channel for monitoring
// Or send to logging service (DataDog, LogRocket, etc.)
```

---

# Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| "Cannot read property 'email' of undefined" | Email not mapped in Zapier | Check input mapping from trigger; ensure field is sent in webhook |
| OpenAI API returns error | Invalid API key or rate limited | Add retry logic, implement exponential backoff |
| Company normalization returns null | No known_companies list provided | Build initial company database or increase OpenAI tolerance |
| Duplicate check fails silently | CRM API key expired | Reconnect CRM integration in Zapier |
| Phone validation too strict | Rejecting valid international formats | Extend phone normalization for multiple countries |

---

# Additional Resources

- [Zapier Code Step Best Practices](https://zapier.com/help/doc/code/js-javascript-code-step)
- [OpenAI API Reference](https://platform.openai.com/docs/api-reference/chat/create)
- [HubSpot Contact API](https://developers.hubspot.com/docs/crm/apis/crm-objects/contacts)
- [Email Validation with Disposable Domains](https://github.com/ivolo/disposable-email-domains)

---

**Last Updated:** December 20, 2025  
**Version:** 1.0 (High-Performance Edition)  
**Next Iteration:** Add fuzzy matching with Levenshtein distance for company deduplication
