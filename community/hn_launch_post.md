# HackerNews / Reddit Launch Draft
**Title:** Show HN: The Neural Content Protocol (NCP) – Stop letting AI scrape your messy DOM

**Content:**
Hey HN,

Over the last year, we've all watched LLMs and AI Agents increasingly act as the primary interface between users and the web. But right now, the way AI models extract data from our websites is broken. 

Currently, an AI agent hitting your site has to download megabytes of React/Vue bundles, parse deeply nested CSS classes, and try to guess what your site is actually offering based on unstructured text. Schema.org and JSON-LD are fantastic for conventional search engine indexing, but their massive vocabulary can be heavy for real-time agentic interactions where an LLM just needs a definitive, deterministic answer.

We got tired of seeing AI agents time out while trying to scrape modern web apps. 

**So we built the Neural Content Protocol (NCP) v1.0.**

NCP is an extremely minimal open specification designed exclusively for AI-to-Web communication. It acts as a Canonical AI Ingestion Layer—a parallel structure to your existing SEO setup. Instead of scraping your DOM, the AI checks a standard meta tag:

`<meta name="ncp-payload-url" content="https://example.com/.well-known/ncp.json">`

It fetches a tiny, strictly-typed JSON payload (capped at 50KB) that answers the only 5 questions an AI needs to understand any web node:
1. **Identity:** Who are you?
2. **Authority:** Why should I not hallucinate you / why trust you?
3. **Entities:** What concepts are absolutely true here?
4. **Offer:** What is the transaction?
5. **Context:** What is the intent and deep summary?

We’ve published the v1.0 strict specification, built validation SDKs in Node.js, Python, and PHP, and established a deterministic compliance matrix (CORE, PLUS, VERIFIED-L1). We also built a public playground validator to test payloads instantly.

**This is not a SaaS. It's an MIT-Licensed Open Standard.**
We are actively looking for the first 50 early adopters to help us shape v1.1. If you ask us "Which AI systems currently consume NCP?", the honest answer is: We are just starting this movement today. 

We'd love for you to try breaking the validator, review the spec, or submit a PR.

- Spec & Repo: `[Link to GitHub]`
- Playground Validator: `https://seoextreme.org/ncp`

Would love to hear your thoughts on standardizing the Agentic Web. Let us know where we got the architecture wrong.
