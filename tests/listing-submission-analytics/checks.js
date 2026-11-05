const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const code = fs.readFileSync('includes/user-manage-listings/listing-submission-analytics.js', 'utf8');
function run(receipt, delayed) {
  const sent = [], timers = [];
  const window = { autoagoraListingSubmission: receipt, setTimeout: fn => timers.push(fn) };
  const gtag = (...args) => sent.push(args);
  if (!delayed) window.gtag = gtag;
  vm.runInNewContext(code, { window });
  if (delayed) { window.gtag = gtag; timers.shift()?.(); }
  return sent;
}
assert.equal(run(null).length, 0);
for (const delayed of [false, true]) {
  const sent = run({ listingId: 42, measurementId: 'G-06RRVCP0GS' }, delayed);
  assert.equal(sent.length, 1);
  assert.equal(sent[0][1], 'listing_submitted');
  assert.equal(sent[0][2].send_to, 'G-06RRVCP0GS');
  assert.equal(sent[0][2].listing_id, 42);
  assert.equal(sent[0][2].listing_status, 'pending');
  assert.equal(Object.keys(sent[0][2]).length, 4);
}
console.log('JavaScript immediate/delayed tag and missing receipt checks passed');
