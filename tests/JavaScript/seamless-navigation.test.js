import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/seamless-navigation.js', import.meta.url), 'utf8');
const styles = readFileSync(new URL('../../public/css/style.css', import.meta.url), 'utf8');

// Execute the production script with controlled DOM, network, and timers.
// Requests deliberately ignore abort so the deadline must settle independently.
function fixture() {
    const roots = new Map();
    const listeners = new Map();
    const timers = new Map();
    const requests = [];
    const toasts = [];
    const sidebar = [];
    const forms = [];
    const assigned = [];
    let timerId = 0;

    class Element {
        constructor(selector = '') {
            this.selector = selector;
            this.dataset = {};
            this.attributes = new Map();
            this.isConnected = true;
            const classes = new Set();
            this.classList = {
                contains: name => classes.has(name),
                toggle(name, enabled) {
                    const add = enabled ?? !classes.has(name);
                    if (add) classes.add(name); else classes.delete(name);
                    return add;
                },
                remove: (...names) => names.forEach(name => classes.delete(name)),
            };
        }
        setAttribute(name, value) { this.attributes.set(name, value); }
        removeAttribute(name) { this.attributes.delete(name); }
        hasAttribute(name) { return this.attributes.has(name); }
        replaceWith(element) { this.isConnected = false; roots.set(this.selector, element); }
        matches() { return false; }
    }
    class Form extends Element {
        constructor(method = 'POST') {
            super();
            this.method = method;
            this.action = 'https://mathverse.test/teacher/classes';
            this.fields = [['class_name', 'Orion']];
            forms.push(this);
        }
    }
    class FakeFormData {
        constructor(form) { this.fields = [...form.fields]; }
        append(name, value) { this.fields.push([name, value]); }
        forEach(callback) { this.fields.forEach(([name, value]) => callback(value, name)); }
    }
    const location = new URL('https://mathverse.test/teacher/dashboard');
    location.assign = url => assigned.push(url);
    const body = new Element();
    const document = {
        body,
        title: 'MathVerse',
        visibilityState: 'visible',
        activeElement: null,
        querySelector(selector) {
            if (selector === 'form[data-mathverse-submitting="true"]') {
                return forms.find(form => form.dataset.mathverseSubmitting === 'true') || null;
            }
            return roots.get(selector) || null;
        },
        querySelectorAll: () => [],
        getElementById: () => null,
        addEventListener(name, callback) {
            if (!listeners.has(name)) listeners.set(name, []);
            listeners.get(name).push(callback);
        },
        dispatchEvent(event) {
            for (const callback of listeners.get(event.type) || []) callback(event);
        },
    };
    const content = new Element('#dashboard-content');
    content.dataset.dashboardRole = 'teacher';
    roots.set('#dashboard-content', content);
    roots.set('#dashboard-modals', new Element('#dashboard-modals'));

    const window = {
        location,
        scrollY: 0,
        scrollTo() {},
        addEventListener() {},
        setTimeout(callback, delay) { timers.set(++timerId, { callback, delay }); return timerId; },
        clearTimeout(id) { timers.delete(id); },
        history: {
            state: null,
            replaceState(state, title, url) { this.state = state; if (url) location.href = url; },
            pushState(state, title, url) { this.state = state; location.href = url; },
        },
    };
    class Parser {
        parseFromString(html) {
            const incoming = new Map();
            if (html !== 'native') {
                const nextContent = new Element('#dashboard-content');
                nextContent.dataset.dashboardRole = 'teacher';
                incoming.set('#dashboard-content', nextContent);
                incoming.set('#dashboard-modals', new Element('#dashboard-modals'));
            }
            return {
                title: 'Loaded page',
                querySelector: selector => incoming.get(selector) || null,
                querySelectorAll: () => [],
            };
        }
    }
    vm.runInNewContext(source, {
        window, document, URL, AbortController, DOMException,
        HTMLElement: Element, HTMLFormElement: Form, FormData: FakeFormData,
        DOMParser: Parser,
        CustomEvent: class { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } },
        navigator: { onLine: true },
        requestAnimationFrame: callback => callback(),
        showToast: (message, error) => toasts.push({ message, error }),
        toggleSidebar: open => sidebar.push(open),
        showSection: () => true,
        displayMathVerseDocumentFeedback() {},
        fetch(url, options) {
            return new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }));
        },
    });
    return {
        window, document, body, requests, toasts, sidebar, assigned, Form, Element,
        fireDeadline(delay) {
            const timer = [...timers.values()].find(entry => entry.delay === delay);
            assert.ok(timer, `A ${delay}ms request deadline must exist`);
            timer.callback();
        },
        response(index, { html = 'dashboard', status = 200, text } = {}) {
            requests[index].resolve({
                ok: status < 400, status, url: requests[index].url,
                headers: { get: () => 'text/html' },
                text: text || (async () => html),
            });
        },
        submit(form, submitter) {
            document.dispatchEvent({ type: 'submit', target: form, submitter, preventDefault() {} });
        },
    };
}

async function flush() {
    for (let i = 0; i < 20; i++) await Promise.resolve();
}

test('a stalled page times out, releases loading, and permits another navigation', async () => {
    const f = fixture();
    const pending = f.window.MathVerseNavigation.navigate('/teacher/classes');
    assert.equal(f.body.classList.contains('mathverse-navigating'), true);
    assert.equal(f.sidebar.at(-1), false);
    f.fireDeadline(20000);
    assert.equal(await pending, false);
    assert.equal(f.requests[0].options.signal.aborted, true);
    assert.equal(f.body.classList.contains('mathverse-navigating'), false);
    assert.match(f.toasts[0].message, /took too long/);

    const next = f.window.MathVerseNavigation.navigate('/teacher/learning-hub');
    f.response(1);
    assert.equal(await next, true);
    assert.equal(f.window.location.pathname, '/teacher/learning-hub');
});

test('the deadline also covers a response body that never arrives', async () => {
    const f = fixture();
    const pending = f.window.MathVerseNavigation.navigate('/teacher/classes');
    f.response(0, { text: () => new Promise(() => {}) });
    await flush();
    f.fireDeadline(20000);
    assert.equal(await pending, false);
    assert.equal(f.body.classList.contains('mathverse-navigating'), false);
});

test('a second click cancels the old GET, including repeated clicks on the same URL', async () => {
    const f = fixture();
    const first = f.window.MathVerseNavigation.navigate('/teacher/classes');
    const second = f.window.MathVerseNavigation.navigate('/teacher/classes');
    assert.equal(f.requests.length, 2);
    assert.equal(f.requests[0].options.signal.aborted, true);
    f.response(1);
    assert.equal(await first, false);
    assert.equal(await second, true);
    assert.equal(f.toasts.length, 0);
    assert.equal(f.body.classList.contains('mathverse-navigating'), false);
});

test('returning to a local dashboard section cancels a pending page request', async () => {
    const f = fixture();
    const first = f.window.MathVerseNavigation.navigate('/teacher/classes');
    assert.equal(await f.window.MathVerseNavigation.navigate('/teacher/dashboard?section=overview', { localSection: true }), true);
    assert.equal(await first, false);
    assert.equal(f.body.classList.contains('mathverse-navigating'), false);
    assert.equal(f.toasts.length, 0);
});

test('a timed-out POST restores the submit button, warns about unknown outcome, and never retries', async () => {
    const f = fixture();
    const form = new f.Form();
    const button = new f.Element();
    f.submit(form, button);
    f.submit(form, button);
    assert.equal(f.requests.length, 1);
    assert.equal(button.disabled, true);
    assert.equal(form.dataset.mathverseSubmitting, 'true');
    f.fireDeadline(30000);
    await flush();
    assert.equal(button.disabled, false);
    assert.equal(form.dataset.mathverseSubmitting, 'false');
    assert.equal(f.body.classList.contains('mathverse-navigating'), false);
    assert.equal(f.requests.length, 1);
    assert.match(f.toasts[0].message, /may already have completed/);
});

test('navigation away does not abort an in-progress mutation or show a stale error', async () => {
    const f = fixture();
    const form = new f.Form();
    const button = new f.Element();
    f.submit(form, button);
    const next = f.window.MathVerseNavigation.navigate('/teacher/learning-hub');
    assert.equal(f.requests[0].options.signal.aborted, false);
    f.response(1);
    assert.equal(await next, true);
    f.fireDeadline(30000);
    await flush();
    assert.equal(f.toasts.length, 0);
    assert.equal(button.disabled, false);
    assert.equal(f.body.classList.contains('mathverse-navigating'), false);
});

test('HTTP errors and non-dashboard responses release loading safely', async () => {
    const f = fixture();
    const error = f.window.MathVerseNavigation.navigate('/teacher/classes');
    f.response(0, { status: 500 });
    assert.equal(await error, false);
    assert.equal(f.body.classList.contains('mathverse-navigating'), false);

    const native = f.window.MathVerseNavigation.navigate('/teacher/learning-hub');
    f.response(1, { html: 'native' });
    assert.equal(await native, false);
    assert.equal(f.assigned[0], 'https://mathverse.test/teacher/learning-hub');
    assert.equal(f.body.classList.contains('mathverse-navigating'), false);
});

test('loading preserves click access and audit outcome badges forbid word splitting', () => {
    const loadingRule = styles.match(/body\.mathverse-navigating #dashboard-content\s*\{([^}]*)\}/)?.[1];
    assert.ok(loadingRule);
    assert.doesNotMatch(loadingRule, /pointer-events:\s*none/);
    const badgeRules = [...styles.matchAll(/\.audit-signal,\s*\.health-badge\s*\{([^}]*)\}/g)]
        .map(match => match[1]).join(' ');
    assert.match(badgeRules, /white-space:\s*nowrap/);
    assert.match(badgeRules, /word-break:\s*normal/);
    assert.match(badgeRules, /overflow-wrap:\s*normal/);
});
