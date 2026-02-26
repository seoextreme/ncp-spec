import urllib.parse
from datetime import datetime

class NcpValidator:
    """
    Neural Content Protocol (NCP) v1.0 Validator
    Official implementation by SEOExtreme
    """
    
    SEMANTIC_TYPES = [
        'universal', 'product', 'article', 'organization', 'service', 'person', 'event'
    ]

    def _add_error(self, code: str, message: str, path: str, severity: str, status_ref: dict):
        error_obj = {"code": code, "severity": severity, "message": message, "path": path}
        if severity == 'BLOCKING':
            status_ref['blocking_errors'].append(error_obj)
        elif severity == 'WARNING':
            status_ref['warnings'].append(error_obj)
        else:
            status_ref['recommendations'].append(error_obj)

    def validate(self, payload: dict, crawled_domain: str = None) -> dict:
        result = {
            "ncp_version": "1.0",
            "status": "FAIL",
            "compliance_level": "NONE",
            "score": 100,
            "blocking_errors": [],
            "warnings": [],
            "recommendations": []
        }

        if not isinstance(payload, dict):
            self._add_error('PAYLOAD_INVALID', 'Input must be a valid JSON dictionary.', '$', 'BLOCKING', result)
            return result

        # 1. Protocol
        if payload.get('protocol') != 'NCP/1.0':
            self._add_error('PROTOCOL_INVALID', 'protocol MUST be exact string "NCP/1.0".', '$.protocol', 'BLOCKING', result)

        # 2. Semantic Type
        sem_type = payload.get('semantic_type')
        if not isinstance(sem_type, str):
            self._add_error('SEMANTIC_TYPE_MISSING', 'semantic_type MUST be a string.', '$.semantic_type', 'BLOCKING', result)
        elif sem_type not in self.SEMANTIC_TYPES:
            self._add_error('SEMANTIC_TYPE_UNKNOWN', f"semantic_type '{sem_type}' is not an official v1.0 enum.", '$.semantic_type', 'WARNING', result)
            result['score'] -= 10

        # 3. Identity
        identity = payload.get('identity')
        if not isinstance(identity, dict):
            self._add_error('IDENTITY_MISSING', 'Identity object is REQUIRED.', '$.identity', 'BLOCKING', result)
        else:
            name = identity.get('name')
            if not isinstance(name, str) or len(name) < 3 or len(name) > 120:
                self._add_error('IDENTITY_NAME_INVALID', 'Identity name MUST be string between 3 and 120 chars.', '$.identity.name', 'BLOCKING', result)
            
            url = identity.get('url')
            if not isinstance(url, str) or not url.startswith('https://'):
                self._add_error('IDENTITY_URL_INVALID', 'Identity url MUST be absolute HTTPS URL.', '$.identity.url', 'BLOCKING', result)
            elif crawled_domain:
                try:
                    parsed_url = urllib.parse.urlparse(url)
                    payload_host = parsed_url.hostname.replace('www.', '') if parsed_url.hostname else ''
                    crawled_host = crawled_domain.replace('www.', '')
                    if payload_host != crawled_host:
                        self._add_error('ORIGIN_MISMATCH', f"Payload URL domain ({payload_host}) != crawled domain ({crawled_host}).", '$.identity.url', 'WARNING', result)
                        result['score'] -= 20
                except Exception:
                    self._add_error('IDENTITY_URL_MALFORMED', 'Identity url is completely malformed.', '$.identity.url', 'BLOCKING', result)

        # 4. Authority
        authority = payload.get('authority')
        if not isinstance(authority, dict):
            self._add_error('AUTHORITY_MISSING', 'Authority object is REQUIRED.', '$.authority', 'BLOCKING', result)
        else:
            if not isinstance(authority.get('verified'), bool):
                self._add_error('AUTHORITY_VERIFIED_INVALID', 'Authority verified MUST be boolean.', '$.authority.verified', 'BLOCKING', result)
            
            score = authority.get('trust_score')
            if not isinstance(score, (int, float)) or score < 0 or score > 10:
                self._add_error('AUTHORITY_SCORE_INVALID', 'Authority trust_score MUST be float between 0.0 and 10.0.', '$.authority.trust_score', 'BLOCKING', result)
            
            signals = authority.get('external_signals')
            if not isinstance(signals, list) or len(signals) > 20:
                self._add_error('AUTHORITY_SIGNALS_INVALID', 'Authority external_signals MUST be array max 20.', '$.authority.external_signals', 'BLOCKING', result)
            else:
                for idx, sig in enumerate(signals):
                    if not isinstance(sig, dict) or 'type' not in sig or 'url' not in sig:
                        self._add_error('AUTHORITY_SIGNAL_MALFORMED', 'All external_signals MUST be objects containing type and url.', f'$.authority.external_signals[{idx}]', 'BLOCKING', result)

        # 5. Entities
        entities = payload.get('entities')
        if not isinstance(entities, dict):
            self._add_error('ENTITIES_MISSING', 'Entities object is REQUIRED.', '$.entities', 'BLOCKING', result)
        else:
            primary = entities.get('primary')
            if not isinstance(primary, list) or len(primary) == 0 or len(primary) > 10:
                self._add_error('ENTITIES_PRIMARY_INVALID', 'Entities primary MUST be array 1-10 elements.', '$.entities.primary', 'BLOCKING', result)

        # 6. Offer
        offer = payload.get('offer')
        if not isinstance(offer, dict):
            self._add_error('OFFER_MISSING', 'Offer object is REQUIRED.', '$.offer', 'BLOCKING', result)
        else:
            if offer.get('price') is not None:
                if not isinstance(offer.get('currency'), str):
                    self._add_error('OFFER_CURRENCY_MISSING', 'Offer currency is REQUIRED when price is present.', '$.offer.currency', 'BLOCKING', result)

        # 7. Context
        context = payload.get('context')
        if not isinstance(context, dict):
            self._add_error('CONTEXT_MISSING', 'Context object is REQUIRED.', '$.context', 'BLOCKING', result)
        else:
            timestamp = context.get('timestamp')
            try:
                # Basic ISO 8601 sanity check
                if not isinstance(timestamp, str): raise ValueError
                datetime.fromisoformat(timestamp.replace('Z', '+00:00'))
            except (ValueError, TypeError):
                self._add_error('CONTEXT_TIMESTAMP_INVALID', 'Context timestamp MUST be valid ISO 8601 date.', '$.context.timestamp', 'BLOCKING', result)
            
            summary = context.get('summary')
            if isinstance(summary, str) and len(summary) < 50:
                self._add_error('CONTEXT_SUMMARY_LENGTH', 'Context summary suspiciously short (< 50 chars).', '$.context.summary', 'WARNING', result)
                result['score'] -= 5

        # --- Compliance Level Algorithm ---
        if result['blocking_errors']:
            result['status'] = 'FAIL'
            result['compliance_level'] = 'NONE'
            result['score'] = 0
            return result

        result['status'] = 'PASS'
        result['compliance_level'] = 'CORE'

        is_enum = sem_type in self.SEMANTIC_TYPES
        has_long_summary = context and isinstance(context.get('summary'), str) and len(context.get('summary')) >= 50
        has_signals = authority and isinstance(authority.get('external_signals'), list) and len(authority.get('external_signals')) > 0

        if is_enum and has_long_summary and has_signals:
            result['compliance_level'] = 'PLUS'
            
            # VERIFIED-L1 Check
            has_origin_warning = any(w['code'] == 'ORIGIN_MISMATCH' for w in result['warnings'])
            is_https = isinstance(identity, dict) and isinstance(identity.get('url'), str) and identity.get('url').startswith('https://')
            is_verified = authority and authority.get('verified') is True
            high_trust = authority and isinstance(authority.get('trust_score'), (int, float)) and authority.get('trust_score') >= 7.0

            if is_https and is_verified and high_trust and not has_origin_warning:
                result['compliance_level'] = 'VERIFIED-L1'

        return result
