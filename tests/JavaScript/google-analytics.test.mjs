import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/google-analytics.js', import.meta.url), 'utf8');
function setup(choice = null, id = 'G-ABC123') {
    const scripts = [];
    const storage = new Map(choice ? [['ga-consent:G-ABC123', JSON.stringify(choice)]] : []);
    const context = {
        window: {}, location: { hostname: 'blog.example.com' },
        localStorage: { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value) },
        document: { cookie: '_ga=old; _ga_ABC123=old; session=keep', getElementById: () => scripts[0], createElement: () => ({}), head: { appendChild: script => scripts.push(script) } },
    };
    vm.runInNewContext(source, context);
    const consent = context.window.googleAnalyticsConsent(id);
    consent.init();
    return { consent, scripts, context, storage };
}
test('no Google request before consent, and rejection stays unloaded', () => {
    const { consent, scripts, context } = setup();
    assert.equal(consent.open, true);
    assert.equal(scripts.length, 0);
    consent.choose(false);
    assert.equal(scripts.length, 0);
    assert.equal(context.window['ga-disable-G-ABC123'], true);
});
test('acceptance loads once, persists, and revocation disables collection', () => {
    const { consent, scripts, context, storage } = setup();
    consent.choose(true);
    consent.choose(true);
    assert.equal(scripts.length, 1);
    assert.match(scripts[0].src, /gtag\/js\?id=G-ABC123$/);
    assert.equal(JSON.parse(storage.get('ga-consent:G-ABC123')).accepted, true);
    consent.choose(false);
    assert.equal(context.window['ga-disable-G-ABC123'], true);
    assert.equal(context.window.dataLayer.at(-1)[2].analytics_storage, 'denied');
});
test('saved acceptance loads while saved rejection and expired choices do not', () => {
    assert.equal(setup({ accepted: true, expires: Date.now() + 10000 }).scripts.length, 1);
    assert.equal(setup({ accepted: false, expires: Date.now() + 10000 }).scripts.length, 0);
    const expired = setup({ accepted: true, expires: Date.now() - 10000 });
    assert.equal(expired.scripts.length, 0);
    assert.equal(expired.consent.open, true);
});

test('notice without a Google tag stores the choice without loading Google', () => {
    const { consent, scripts, storage } = setup(null, null);
    assert.equal(consent.open, true);
    consent.choose(true);
    assert.equal(consent.open, false);
    assert.equal(scripts.length, 0);
    assert.equal(JSON.parse(storage.get('ga-consent:privacy-notice')).accepted, true);
});
