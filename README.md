# Neural Content Protocol (NCP) v1.0
**Status: Stable – Production Ready**

> **AI Communication Layer for the Web**
> 
> *NCP defines a minimal, structured, machine-readable layer for AI systems to access authoritative, noise-free metadata about a web resource. It acts as a canonical AI ingestion layer.*

## The Problem: AI Ingestion Noise
When an AI (LLM, Autonomous Agent, or Crawler) hits a modern web page, it has to download megabytes of React/Vue bundles, parse deeply nested `<div class="css-1x2y3z">` structures, and guess what your content is actually about.

**Why not just Schema.org / JSON-LD?**
Schema.org is a fantastic, highly-flexible vocabulary that serves search engines and human-centric SEO use cases perfectly. However, because of its broad nature, it often requires AI agents to map hundreds of potential object types. 

**NCP is a parallel evolution.** It is strictly typed, brutally minimal (capped at 50KB), and focuses exclusively on split-second, deterministic context-gathering for autonomous agents, leaving search indexing to Schema.org.

## 1. Discovery Mechanism
AI Crawlers strictly looking for authoritative data MUST follow one of two paths:

**Option A (Preferred):**
```html
<meta name="ncp-payload-url" content="https://example.com/.well-known/ncp.json">
```

**Option B (Fallback):**
```http
GET /.well-known/ncp.json
```

## 2. Payload Core (The 5 Pillars)
NCP v1.0 standardizes web data into 5 fundamental concepts:
1. `identity` (Who is this?)
2. `authority` (Why trust it?)
3. `entities` (What concepts are discussed?)
4. `offer` (What is the action/transaction?)
5. `context` (Semantic depth and audience)

### Example Payload
```json
{
  "protocol": "NCP/1.0",
  "semantic_type": "universal",
  "identity": {
      "name": "SEOExtreme",
      "description": "Next-Generation SEO tooling for the AI Web.",
      "url": "https://seoextreme.org/",
      "image": "https://seoextreme.org/logo.png"
  },
  "authority": {
      "verified": true,
      "trust_score": 9.5,
      "external_signals": [
          {"type": "github", "url": "https://github.com/seoextreme/ncp-spec"}
      ]
  },
  "entities": {
      "primary": ["AI Communication", "Protocol"],
      "secondary": []
  },
  "offer": {
      "type": "Information",
      "price": null,
      "currency": null
  },
  "context": {
      "intent": "Informational",
      "target_audience": "Developers",
      "language": "en",
      "summary": "Full overview of the protocol mechanism.",
      "timestamp": "2026-02-26T12:00:00Z"
  }
}
```

## 3. Compliance Levels

* **NONE:** Blocking errors present.
* **CORE (Minimum Compliance):** Valid discovery + valid protocol field + all 5 pillars present.
* **PLUS:** CORE + semantic type is official enum + summary > 50 chars + external signals active.
* **VERIFIED-L1 (Origin Strict):** PLUS + HTTPS + trust_score >= 7.0 + Canonical Origin matches (payload root domain matches crawled domain).

*(Formal Cryptographic signature reserved for v1.1)*

## 4. Minor Version Policy
Implementations MUST accept `NCP/1.0` for the lifetime of major version 1.x. Breaking changes will only occur in `NCP/2.0`.

## 5. Developer Limits
Ingestion crawlers SHOULD timeout after `≤ 3 seconds` and follow a maximum of `1 redirect` to strictly prevent stalling. MAY retry once on timeout. Payload size strictly capped at `50KB`.

## 6. Community & Governance

Neural Content Protocol is an Open Standard (MIT Licensed). It is not vendor-locked to SEOExtreme. 

**Governance Matrix:**
- **RFC Proposals (v2.0+):** Managed strictly through GitHub Issues via the `rfc-proposal` tag.
- **Minor Versions (v1.x):** Non-breaking additions are accepted via Pull Requests to the `main` branch. All PRs must successfully pass against the `test-suite/pass_vectors.json`.
- **Implementations:** If you build a validation engine or SDK in a new language, please submit a PR to the `sdks/` directory.

### FAQs

**Isn't this just JSON-LD reinvented?**
No. JSON-LD is excellent for broad search engine indexing using the massive Schema.org vocabulary. NCP is a specialized, parallel layer providing exactly 5 strict pillars designed for real-time AI ingestion. If a payload does not meet these 5 pillars, it is invalid. They can and should coexist perfectly on the same domain.

**How is this different from existing meta tags?**
Meta tags (like OpenGraph or Twitter Cards) are designed for social media link previews (Title, Description, Image). They do not provide context, pricing, verifiable authority scoring, or standardized semantic typing for LLM consumption.

**Where is real-world proof?**
To see how an AI Agent processes an NCP payload versus a React DOM, refer to our [Zero to AI-Native Use Case](community/use_case_zero_to_ai_native.md).

---
*Built openly for the incoming Agentic Web.*
