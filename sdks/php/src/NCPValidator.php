<?php

namespace SEOExtreme\NCP;

class NCPValidator
{
    /**
     * Add a structured error object to the corresponding array
     */
    private function addError(&$array, $severity, $code, $path, $message)
    {
        $array[] = [
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
            'path' => $path
        ];
    }

    /**
     * Parse and validate an NCP payload array against the v1.0 strict RFC definition.
     *
     * @param array $payload
     * @param string|null $sourceDomain The domain we are crawling (to match with payload URLs)
     * @return array
     */
    public function validate(array $payload, $sourceDomain = null)
    {
        $status = 'PASS';
        $complianceLevel = 'NONE';
        $score = 100;
        $blockingErrors = [];
        $warnings = [];
        $recommendations = [];

        // 1. Root Structure & Discovery
        if (empty($payload)) {
            $this->addError($blockingErrors, 'BLOCKING', 'PAYLOAD_EMPTY', '$', 'Payload is empty or null.');
            return $this->formatResult('FAIL', 'NONE', 0, $blockingErrors, [], []);
        }

        // 4.1 protocol (REQUIRED)
        if (!isset($payload['protocol'])) {
            $this->addError($blockingErrors, 'BLOCKING', 'PROTOCOL_MISSING', '$.protocol', "Missing required root field: 'protocol'.");
        } elseif (!preg_match('/^NCP\/\d+\.\d+$/', $payload['protocol'])) {
            $this->addError($blockingErrors, 'BLOCKING', 'PROTOCOL_INVALID', '$.protocol', "Invalid protocol format. MUST match '^NCP\/\d+\.\d+$' (e.g., 'NCP/1.0').");
        }

        // 4.2 semantic_type (REQUIRED)
        $allowedSemanticTypes = ['universal', 'product', 'article', 'organization', 'service', 'person', 'event'];
        if (!isset($payload['semantic_type'])) {
            $this->addError($blockingErrors, 'BLOCKING', 'SEMANTIC_TYPE_MISSING', '$.semantic_type', "Missing required root field: 'semantic_type'.");
        } elseif (!in_array(strtolower($payload['semantic_type']), $allowedSemanticTypes)) {
            $this->addError($warnings, 'WARNING', 'SEMANTIC_TYPE_UNKNOWN', '$.semantic_type', "Unknown semantic_type value ('{$payload['semantic_type']}'). Values should be from official v1.0 enum.");
        }

        // Check pillars presence
        $pillars = ['identity', 'authority', 'entities', 'offer', 'context'];
        foreach ($pillars as $pillar) {
            if (!isset($payload[$pillar]) || !is_array($payload[$pillar])) {
                $this->addError($blockingErrors, 'BLOCKING', 'PILLAR_MISSING', '$.' . $pillar, "Missing or malformed required pillar: '{$pillar}'.");
            }
        }

        // If core pillars are missing, we cannot do deep validation of them
        if (empty($blockingErrors)) {

            // 5.1 Identity
            $i = $payload['identity'];
            if (empty($i['name'])) {
                $this->addError($blockingErrors, 'BLOCKING', 'IDENTITY_NAME_MISSING', '$.identity.name', "Identity error: missing 'name'.");
            } elseif (mb_strlen($i['name']) < 3 || mb_strlen($i['name']) > 120) {
                $this->addError($warnings, 'WARNING', 'IDENTITY_NAME_LENGTH', '$.identity.name', "Identity warning: 'name' should be between 3 and 120 characters.");
            }

            if (empty($i['description'])) {
                $this->addError($warnings, 'WARNING', 'IDENTITY_DESC_MISSING', '$.identity.description', "Identity warning: missing 'description'.");
            } elseif (mb_strlen($i['description']) < 10 || mb_strlen($i['description']) > 500) {
                $this->addError($warnings, 'WARNING', 'IDENTITY_DESC_LENGTH', '$.identity.description', "Identity warning: 'description' should be between 10 and 500 characters.");
            }

            if (empty($i['url'])) {
                $this->addError($blockingErrors, 'BLOCKING', 'IDENTITY_URL_MISSING', '$.identity.url', "Identity error: missing 'url'.");
            } elseif (!filter_var($i['url'], FILTER_VALIDATE_URL) || !str_starts_with(strtolower($i['url']), 'https://')) {
                $this->addError($blockingErrors, 'BLOCKING', 'IDENTITY_URL_INVALID', '$.identity.url', "Identity error: 'url' MUST be an absolute HTTPS URL.");
            }

            if (!empty($i['image']) && (!filter_var($i['image'], FILTER_VALIDATE_URL) || !str_starts_with(strtolower($i['image']), 'https://'))) {
                $this->addError($blockingErrors, 'BLOCKING', 'IDENTITY_IMAGE_INVALID', '$.identity.image', "Identity error: 'image' MUST be an absolute HTTPS URL.");
            }

            if (empty($i['author'])) {
                $this->addError($warnings, 'WARNING', 'IDENTITY_AUTHOR_MISSING', '$.identity.author', "Identity warning: missing 'author' (optional but recommended).");
            }

            // 5.2 Authority
            $auth = $payload['authority'];
            if (!isset($auth['verified']) || !is_bool($auth['verified'])) {
                $this->addError($blockingErrors, 'BLOCKING', 'AUTHORITY_VERIFIED_INVALID', '$.authority.verified', "Authority error: 'verified' MUST be a boolean.");
            }
            if (!isset($auth['trust_score']) || !is_numeric($auth['trust_score']) || $auth['trust_score'] < 0 || $auth['trust_score'] > 10) {
                $this->addError($blockingErrors, 'BLOCKING', 'AUTHORITY_TRUST_SCORE_INVALID', '$.authority.trust_score', "Authority error: 'trust_score' MUST be a number between 0.0 and 10.0.");
            }

            if (!isset($auth['external_signals']) || !is_array($auth['external_signals'])) {
                $this->addError($blockingErrors, 'BLOCKING', 'AUTHORITY_SIGNALS_INVALID', '$.authority.external_signals', "Authority error: 'external_signals' MUST be an array.");
            } elseif (empty($auth['external_signals'])) {
                $this->addError($recommendations, 'RECOMMENDATION', 'AUTHORITY_SIGNALS_EMPTY', '$.authority.external_signals', "Consider adding external links/proofs to 'external_signals'.");
            } else {
                // Strict typing for external_signals objects
                foreach ($auth['external_signals'] as $idx => $signal) {
                    if (!is_array($signal) || empty($signal['type']) || empty($signal['url'])) {
                        $this->addError($blockingErrors, 'BLOCKING', 'AUTHORITY_SIGNAL_MALFORMED', '$.authority.external_signals[' . $idx . ']', "Authority error: Each external_signal MUST be an object with 'type' and 'url' properties.");
                    } elseif (!filter_var($signal['url'], FILTER_VALIDATE_URL) || !str_starts_with(strtolower($signal['url']), 'https://')) {
                        $this->addError($blockingErrors, 'BLOCKING', 'AUTHORITY_SIGNAL_URL_INVALID', '$.authority.external_signals[' . $idx . '].url', "Authority error: external_signal 'url' MUST be HTTPS.");
                    }
                }
                if (count($auth['external_signals']) > 20) {
                    $this->addError($blockingErrors, 'BLOCKING', 'AUTHORITY_SIGNALS_LIMIT', '$.authority.external_signals', "Authority error: 'external_signals' max count is 20.");
                }
            }

            // 5.3 Entities
            $ent = $payload['entities'];
            if (!isset($ent['primary']) || !is_array($ent['primary']) || empty($ent['primary'])) {
                $this->addError($blockingErrors, 'BLOCKING', 'ENTITIES_PRIMARY_MISSING', '$.entities.primary', "Entities error: 'primary' MUST be a non-empty array.");
            } else {
                if (count($ent['primary']) > 10) {
                    $this->addError($blockingErrors, 'BLOCKING', 'ENTITIES_PRIMARY_LIMIT', '$.entities.primary', "Entities error: 'primary' exceeds maximum length of 10.");
                }
                if (count($ent['primary']) !== count(array_unique($ent['primary']))) {
                    $this->addError($blockingErrors, 'BLOCKING', 'ENTITIES_PRIMARY_DUPLICATE', '$.entities.primary', "Entities error: 'primary' values MUST be unique.");
                }
            }
            if (empty($ent['secondary']) || !is_array($ent['secondary'])) {
                $this->addError($recommendations, 'RECOMMENDATION', 'ENTITIES_SECONDARY_EMPTY', '$.entities.secondary', "Consider adding 'secondary' entities for richer semantic depth.");
            }

            // 5.4 Offer
            $off = $payload['offer'];
            if (isset($off['price']) && $off['price'] !== null) {
                if (empty($off['currency'])) {
                    $this->addError($blockingErrors, 'BLOCKING', 'OFFER_CURRENCY_MISSING', '$.offer.currency', "Offer error: 'currency' is REQUIRED when 'price' is present.");
                } elseif (strlen($off['currency']) !== 3) {
                    $this->addError($blockingErrors, 'BLOCKING', 'OFFER_CURRENCY_INVALID', '$.offer.currency', "Offer error: 'currency' format invalid. MUST match ISO 4217.");
                }
            }

            // 5.5 Context
            $ctx = $payload['context'];
            if (!empty($ctx['timestamp']) && strtotime($ctx['timestamp']) === false) {
                $this->addError($blockingErrors, 'BLOCKING', 'CONTEXT_TIMESTAMP_INVALID', '$.context.timestamp', "Context error: 'timestamp' MUST be a valid ISO 8601 date.");
            }
            if (!empty($ctx['language']) && strlen($ctx['language']) > 5) {
                // Approximate ISO lang check
                $this->addError($blockingErrors, 'BLOCKING', 'CONTEXT_LANGUAGE_INVALID', '$.context.language', "Context error: 'language' format invalid. Expected ISO 639-1 format.");
            }
            if (empty($ctx['summary']) || mb_strlen($ctx['summary']) < 50) {
                $this->addError($warnings, 'WARNING', 'CONTEXT_SUMMARY_LENGTH', '$.context.summary', "Context warning: 'summary' length is suspiciously short (< 50 chars).");
            }
        }

        // Canonicalization Origin Check
        $originMatch = false;
        if ($sourceDomain !== null && !empty($payload['identity']['url'])) {
            $payloadDomain = parse_url($payload['identity']['url'], PHP_URL_HOST);
            // Ignore www prefix and handle case-insensitive match
            $cleanSource = preg_replace('/^www\./', '', strtolower($sourceDomain));
            $cleanPayload = preg_replace('/^www\./', '', strtolower($payloadDomain));
            $originMatch = ($cleanSource === $cleanPayload);

            if (!$originMatch) {
                $this->addError($warnings, 'WARNING', 'IDENTITY_ORIGIN_MISMATCH', '$.identity.url', "Origin mismatch: Payload URL domain '{$cleanPayload}' does not match crawled domain '{$cleanSource}'.");
            }
        }

        // Determine Compliance Level
        if (!empty($blockingErrors)) {
            $status = 'FAIL';
            $complianceLevel = 'NONE';
            $score -= 100; // Total failure
        } else {
            // Core Compliance Achieved
            $complianceLevel = 'CORE';
            $score = 80;

            // Check if PLUS
            $isPlus = true;
            if (
                !in_array(strtolower($payload['semantic_type'] ?? ''), $allowedSemanticTypes) ||
                empty($payload['authority']['external_signals']) ||
                strlen($payload['context']['summary'] ?? '') < 50
            ) {
                $isPlus = false;
            }

            if ($isPlus) {
                $complianceLevel = 'PLUS';
                $score = 90;

                // Check if VERIFIED
                $isVerified = true;
                if (
                    empty($payload['authority']['verified']) || $payload['authority']['verified'] !== true ||
                    $payload['authority']['trust_score'] < 7.0 ||
                    !str_starts_with(strtolower($payload['identity']['url'] ?? ''), 'https://') ||
                    !$originMatch // Origin canonicalization enforced for L1 Verification
                ) {
                    $isVerified = false;
                }

                if ($isVerified) {
                    $complianceLevel = 'VERIFIED-L1';
                    $score = 100;
                }
            }

            if (!empty($warnings)) {
                if ($status === 'PASS') {
                    $status = 'WARNING';
                }
                $score -= (count($warnings) * 5);
            }
        }

        return $this->formatResult($status, $complianceLevel, max(0, $score), $blockingErrors, $warnings, $recommendations);
    }

    private function formatResult($status, $level, $score, $errors, $warnings, $recs)
    {
        return [
            "ncp_version" => "1.0",
            "status" => $status,
            "compliance_level" => $level,
            "score" => $score,
            "blocking_errors" => $errors,
            "warnings" => $warnings,
            "recommendations" => $recs
        ];
    }
}
