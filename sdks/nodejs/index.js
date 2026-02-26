/**
 * Neural Content Protocol (NCP) v1.0 Validator
 * Official implementation by SEOExtreme
 */

class NcpValidator {
  constructor() {
    this.semanticTypes = [
      'universal', 'product', 'article', 'organization', 'service', 'person', 'event'
    ];
  }

  /**
   * Add a structured error to the tracking arrays
   */
  _addError(code, message, path, severity = 'BLOCKING', statusRef) {
    const errorObj = { code, severity, message, path };
    if (severity === 'BLOCKING') {
      statusRef.blocking_errors.push(errorObj);
    } else if (severity === 'WARNING') {
      statusRef.warnings.push(errorObj);
    } else {
      statusRef.recommendations.push(errorObj);
    }
  }

  /**
   * Main validation engine
   * @param {Object} payload The parsed incoming JSON object
   * @param {string} crawledDomain The origin domain you requested the payload from (e.g., example.com)
   * @returns {Object} Structured validation output
   */
  validate(payload, crawledDomain = null) {
    const result = {
      ncp_version: '1.0',
      status: 'FAIL',
      compliance_level: 'NONE',
      score: 100,
      blocking_errors: [],
      warnings: [],
      recommendations: []
    };

    if (!payload || typeof payload !== 'object') {
      this._addError('PAYLOAD_INVALID', 'Input must be a valid JSON object.', '$', 'BLOCKING', result);
      return result;
    }

    // 1. Protocol Version
    if (payload.protocol !== 'NCP/1.0') {
      this._addError('PROTOCOL_INVALID', 'protocol MUST be exact string "NCP/1.0".', '$.protocol', 'BLOCKING', result);
    }

    // 2. Semantic Type
    if (!payload.semantic_type || typeof payload.semantic_type !== 'string') {
      this._addError('SEMANTIC_TYPE_MISSING', 'semantic_type MUST be a string.', '$.semantic_type', 'BLOCKING', result);
    } else if (!this.semanticTypes.includes(payload.semantic_type)) {
      this._addError('SEMANTIC_TYPE_UNKNOWN', `semantic_type '${payload.semantic_type}' is not an official v1.0 enum.`, '$.semantic_type', 'WARNING', result);
      result.score -= 10;
    }

    // 3. Identity
    if (!payload.identity || typeof payload.identity !== 'object') {
      this._addError('IDENTITY_MISSING', 'Identity object is REQUIRED.', '$.identity', 'BLOCKING', result);
    } else {
      const id = payload.identity;
      if (!id.name || typeof id.name !== 'string' || id.name.length < 3 || id.name.length > 120) {
        this._addError('IDENTITY_NAME_INVALID', 'Identity name MUST be a string between 3 and 120 characters.', '$.identity.name', 'BLOCKING', result);
      }
      if (!id.url || typeof id.url !== 'string' || !id.url.startsWith('https://')) {
        this._addError('IDENTITY_URL_INVALID', 'Identity url MUST be an absolute HTTPS URL.', '$.identity.url', 'BLOCKING', result);
      } else if (crawledDomain) {
        try {
          const payloadUrlObj = new URL(id.url);
          const payloadHost = payloadUrlObj.hostname.replace(/^www\./, '');
          const crawledHost = crawledDomain.replace(/^www\./, '');
          if (payloadHost !== crawledHost) {
            this._addError('ORIGIN_MISMATCH', `Payload URL domain (${payloadHost}) does not match crawled domain (${crawledHost}).`, '$.identity.url', 'WARNING', result);
            result.score -= 20;
          }
        } catch (e) {
          this._addError('IDENTITY_URL_MALFORMED', 'Identity url is completely malformed.', '$.identity.url', 'BLOCKING', result);
        }
      }
    }

    // 4. Authority
    if (!payload.authority || typeof payload.authority !== 'object') {
      this._addError('AUTHORITY_MISSING', 'Authority object is REQUIRED.', '$.authority', 'BLOCKING', result);
    } else {
      const auth = payload.authority;
      if (typeof auth.verified !== 'boolean') {
        this._addError('AUTHORITY_VERIFIED_INVALID', 'Authority verified MUST be a boolean.', '$.authority.verified', 'BLOCKING', result);
      }
      if (typeof auth.trust_score !== 'number' || auth.trust_score < 0 || auth.trust_score > 10) {
        this._addError('AUTHORITY_SCORE_INVALID', 'Authority trust_score MUST be a float between 0.0 and 10.0.', '$.authority.trust_score', 'BLOCKING', result);
      }
      if (!Array.isArray(auth.external_signals) || auth.external_signals.length > 20) {
        this._addError('AUTHORITY_SIGNALS_INVALID', 'Authority external_signals MUST be an array mapped to max 20 elements.', '$.authority.external_signals', 'BLOCKING', result);
      } else {
        auth.external_signals.forEach((sig, index) => {
          if (!sig || typeof sig !== 'object' || !sig.type || !sig.url) {
            this._addError('AUTHORITY_SIGNAL_MALFORMED', 'All external_signals MUST be objects containing type and url.', `$.authority.external_signals[${index}]`, 'BLOCKING', result);
          }
        });
      }
    }

    // 5. Entities
    if (!payload.entities || typeof payload.entities !== 'object') {
      this._addError('ENTITIES_MISSING', 'Entities object is REQUIRED.', '$.entities', 'BLOCKING', result);
    } else {
      const ent = payload.entities;
      if (!Array.isArray(ent.primary) || ent.primary.length === 0 || ent.primary.length > 10) {
         this._addError('ENTITIES_PRIMARY_INVALID', 'Entities primary MUST be an array with 1 to 10 elements.', '$.entities.primary', 'BLOCKING', result);
      }
    }

    // 6. Offer
    if (!payload.offer || typeof payload.offer !== 'object') {
      this._addError('OFFER_MISSING', 'Offer object is REQUIRED.', '$.offer', 'BLOCKING', result);
    } else {
      const off = payload.offer;
      if (off.price !== null && off.price !== undefined) {
        if (!off.currency || typeof off.currency !== 'string') {
          this._addError('OFFER_CURRENCY_MISSING', 'Offer currency is REQUIRED when price is present.', '$.offer.currency', 'BLOCKING', result);
        }
      }
    }

    // 7. Context
    if (!payload.context || typeof payload.context !== 'object') {
      this._addError('CONTEXT_MISSING', 'Context object is REQUIRED.', '$.context', 'BLOCKING', result);
    } else {
      const ctx = payload.context;
      if (!ctx.timestamp || isNaN(Date.parse(ctx.timestamp))) {
        this._addError('CONTEXT_TIMESTAMP_INVALID', 'Context timestamp MUST be a valid ISO 8601 date.', '$.context.timestamp', 'BLOCKING', result);
      }
      if (ctx.summary && typeof ctx.summary === 'string' && ctx.summary.length < 50) {
        this._addError('CONTEXT_SUMMARY_LENGTH', 'Context summary length is suspiciously short (< 50 chars).', '$.context.summary', 'WARNING', result);
        result.score -= 5;
      }
    }

    // --- Compliance Level Algorithm ---
    if (result.blocking_errors.length > 0) {
      result.status = 'FAIL';
      result.compliance_level = 'NONE';
      result.score = 0;
      return result;
    }

    // At this point, we have CORE compliance. Let's check PLUS and VERIFIED-L1
    result.status = 'PASS';
    result.compliance_level = 'CORE';

    const hasWarnings = result.warnings.length > 0;
    const isEnum = this.semanticTypes.includes(payload.semantic_type);
    const hasLongSummary = payload.context && typeof payload.context.summary === 'string' && payload.context.summary.length >= 50;
    const hasSignals = payload.authority && Array.isArray(payload.authority.external_signals) && payload.authority.external_signals.length > 0;

    if (isEnum && hasLongSummary && hasSignals) {
       result.compliance_level = 'PLUS';
       
       // Check VERIFIED-L1 rules
       const hasOriginWarning = result.warnings.some(w => w.code === 'ORIGIN_MISMATCH');
       const isHttps = payload.identity && payload.identity.url && payload.identity.url.startsWith('https://');
       const isVerified = payload.authority && payload.authority.verified === true;
       const highTrust = payload.authority && payload.authority.trust_score >= 7.0;

       if (isHttps && isVerified && highTrust && !hasOriginWarning) {
           result.compliance_level = 'VERIFIED-L1';
       }
    }

    return result;
  }
}

module.exports = { NcpValidator };
