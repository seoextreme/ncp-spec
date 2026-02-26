const { NcpValidator } = require('./index');
const fs = require('fs');
const path = require('path');

const validator = new NcpValidator();

// Load the official test suites from the repo
const passSuiteRaw = fs.readFileSync(path.resolve(__dirname, '../../test-suite/pass_vectors.json'), 'utf8');
const failSuiteRaw = fs.readFileSync(path.resolve(__dirname, '../../test-suite/fail_vectors.json'), 'utf8');

const passSuite = JSON.parse(passSuiteRaw);
const failSuite = JSON.parse(failSuiteRaw);

console.log('--- RUNNING NCP v1.0 NODE.JS VALIDATOR TESTS ---\n');

let passCount = 0;
let failCount = 0;

console.log('--- 1. PASS VECTORS ---');
passSuite.vectors.forEach((vector, i) => {
    // For origin matching testing, we simulate crawling from seoextreme.org
    const result = validator.validate(vector, 'seoextreme.org');
    if (result.status === 'PASS') {
        passCount++;
        console.log(`✅ Vector ${i + 1} passed as expected (Level: ${result.compliance_level}).`);
    } else {
        console.error(`❌ Vector ${i + 1} FAILED unexpectedly.`, result);
    }
});

console.log('\n--- 2. FAIL VECTORS ---');
failSuite.vectors.forEach((testCase, i) => {
    const result = validator.validate(testCase.payload);
    if (result.status === 'FAIL') {
        failCount++;
        console.log(`✅ ${testCase.title} failed as expected (Errors: ${result.blocking_errors.length}).`);
    } else {
        console.error(`❌ ${testCase.title} PASSED unexpectedly.`, result);
    }
});

console.log(`\nRESULTS: ${passCount}/${passSuite.vectors.length} PASS Vectors OK, ${failCount}/${failSuite.vectors.length} FAIL Vectors OK.`);
if (passCount !== passSuite.vectors.length || failCount !== failSuite.vectors.length) {
    process.exit(1);
} else {
    console.log('🎉 Node.js SDK is structurally sound and compliant with v1.0 specifications.');
}
