// Loads the real toolbar.js with just enough of a browser around it, runs the scenarios
// below against DopparProfiler.mergeAjaxHistory(), and prints the result as JSON.
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/assets/toolbar.js'), 'utf8');

function loadToolbar(pathname) {
  const noop = () => {};
  const element = () => ({ style: {}, setAttribute: noop, appendChild: noop, addEventListener: noop, querySelector: () => null, querySelectorAll: () => [], classList: { add: noop, remove: noop, toggle: noop } });
  const sandbox = {
    console,
    navigator: {},
    performance: { now: () => 0 },
    fetch: () => Promise.resolve({ ok: false }),
    URL,
    Date,
    Promise,
    setTimeout: noop,
    requestAnimationFrame: noop,
    document: { getElementById: () => null, createElement: element, body: element(), documentElement: element(), addEventListener: noop, querySelector: () => null, querySelectorAll: () => [] },
  };
  const XHR = function () {};
  XHR.prototype.open = noop;
  XHR.prototype.send = noop;
  sandbox.XMLHttpRequest = XHR;
  sandbox.window = sandbox;
  sandbox.window.location = { pathname, href: 'http://app.test' + pathname };
  sandbox.window.addEventListener = noop;
  sandbox.window.localStorage = { getItem: () => null, setItem: noop };
  sandbox.window.fetch = sandbox.fetch;
  vm.runInNewContext(source, sandbox);
  return sandbox.window.DopparProfiler;
}

const now = Math.floor(Date.now() / 1000);
const page = { time_start: now };
const ajax = (id, overrides = {}) => ({ id, is_ajax: true, method: 'GET', route: '/users/10/edit', status: 200, duration_ms: 12, captured_at_unix: now + 1, ...overrides });

function merged(pathname, history, pageData = page) {
  const profiler = loadToolbar(pathname);
  profiler.mergeAjaxHistory(history, pageData);
  return profiler.liveAjax.map((entry) => entry.id);
}

const results = {
  // the reported case: /home was opened after the Users page made its AJAX calls
  earlierPagesAjaxIsIgnored: merged('/home', [
    ajax('from-users-page-1', { captured_at_unix: now - 120, referer: 'http://app.test/users' }),
    ajax('from-users-page-2', { captured_at_unix: now - 90 }),
  ]),
  ajaxMadeByThisPageIsKept: merged('/home', [ajax('mine', { referer: 'http://app.test/home' })]),
  ajaxFromAnotherPageAfterLoadIsIgnored: merged('/home', [ajax('other-tab', { referer: 'http://app.test/users' })]),
  ajaxWithoutARefererIsKeptWhenItIsRecent: merged('/home', [ajax('no-referer')]),
  refererQueryHashAndTrailingSlashAreIgnored: merged('/home/', [ajax('same-page', { referer: 'http://app.test/home?tab=2#top' })]),
  rootPageMatchesItsOwnReferer: merged('/', [ajax('root', { referer: 'http://app.test/' })]),
  rootAjaxIsNotShownOnAnotherPage: merged('/home', [ajax('root-ajax', { referer: 'http://app.test/' })]),
  requestsThatAreNotAjaxAreIgnored: merged('/home', [ajax('plain', { is_ajax: false, referer: 'http://app.test/home' })]),
  sameSecondAsThePageCounts: merged('/home', [ajax('same-second', { captured_at_unix: now })]),
  oneSecondBeforeThePageIsIgnored: merged('/home', [ajax('just-before', { captured_at_unix: now - 1 })]),
  unknownTimestampIsNotDiscarded: merged('/home', [ajax('unknown-time', { captured_at_unix: 0, referer: 'http://app.test/home' })]),
  withoutAPageStartOnlyTheRefererDecides: merged('/home', [ajax('old', { captured_at_unix: 5, referer: 'http://app.test/home' }), ajax('elsewhere', { captured_at_unix: 5, referer: 'http://app.test/users' })], {}),
  anUnparseableRefererDoesNotHideTheRequest: merged('/home', [ajax('odd-referer', { referer: 'http://' })]),
  everythingTogether: merged('/home', [
    ajax('a', { captured_at_unix: now - 300, referer: 'http://app.test/users' }),
    ajax('b', { referer: 'http://app.test/home' }),
    ajax('c', { referer: 'http://app.test/tags' }),
    ajax('d', { is_ajax: false }),
    ajax('e'),
  ]),
  badHistoryIsHarmless: (() => { const p = loadToolbar('/home'); p.mergeAjaxHistory(null, page); p.mergeAjaxHistory('nope', page); return p.liveAjax.length; })(),
};

process.stdout.write(JSON.stringify(results));
