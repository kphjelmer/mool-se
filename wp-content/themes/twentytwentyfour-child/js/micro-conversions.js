/**
 * Mool – mikrokonverteringar
 * Skickar events till GA4 (via GTM) och Google Ads.
 *
 * Google Ads conversion-labels: fyll i AW_LABELS när du skapat
 * conversion actions i Google Ads-gränssnittet.
 */
(function () {
  'use strict';

  // ── Konfiguration ────────────────────────────────────────────────────────
  var AW_ID = 'AW-1045557188';

  // Fyll i labels efter att ha skapat conversion actions i Google Ads.
  // Tom sträng = bara GA4-event skickas (Google Ads hoppas över).
  var AW_LABELS = {
    engaged_session:    '',
    deep_scroll:        '',
    cta_click_boka_tid: '',
    email_click:        '',
  };

  // ── Hjälpfunktioner ──────────────────────────────────────────────────────
  function sendEvent(name, params) {
    if (typeof gtag !== 'function') return;

    // GA4
    gtag('event', name, params || {});

    // Google Ads (om label är ifylld)
    if (AW_LABELS[name]) {
      gtag('event', 'conversion', { send_to: AW_ID + '/' + AW_LABELS[name] });
    }
  }

  function sessionFlag(key) {
    return {
      isSet: function () { return sessionStorage.getItem(key) === '1'; },
      set:   function () { sessionStorage.setItem(key, '1'); },
    };
  }

  var page = window.location.pathname;

  // ── 1. engaged_session ──────────────────────────────────────────────────
  // Triggas när besökaren varit ≥60 sek på sidan OCH scrollat förbi 100vh.
  // Max 1 gång per session.
  (function () {
    var flag = sessionFlag('mool_engaged_session');
    if (flag.isSet()) return;

    var timeOk   = false;
    var scrollOk = false;
    var fired    = false;

    function maybeFireEngaged() {
      if (fired || !timeOk || !scrollOk) return;
      fired = true;
      flag.set();
      sendEvent('engaged_session', { page: page });
    }

    setTimeout(function () {
      timeOk = true;
      maybeFireEngaged();
    }, 60000);

    var ticking = false;
    window.addEventListener('scroll', function () {
      if (scrollOk || ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        ticking = false;
        if (!scrollOk && window.scrollY >= window.innerHeight) {
          scrollOk = true;
          maybeFireEngaged();
        }
      });
    }, { passive: true });
  })();

  // ── 2. deep_scroll ──────────────────────────────────────────────────────
  // Triggas när besökaren scrollat till 80% av sidans totala höjd.
  // Max 1 gång per session.
  (function () {
    var flag = sessionFlag('mool_deep_scroll');
    if (flag.isSet()) return;

    var fired   = false;
    var ticking = false;

    function onScroll() {
      if (fired || ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        ticking = false;
        if (fired) return;
        var scrolled  = window.scrollY + window.innerHeight;
        var docHeight = document.documentElement.scrollHeight;
        if (scrolled >= docHeight * 0.8) {
          fired = true;
          flag.set();
          sendEvent('deep_scroll', { page: page });
          window.removeEventListener('scroll', onScroll);
        }
      });
    }

    window.addEventListener('scroll', onScroll, { passive: true });
  })();

  // ── 3. cta_click_boka_tid ───────────────────────────────────────────────
  // Triggas vid klick på "Boka tid"-knappar och bokningslänkar.
  (function () {
    document.addEventListener('click', function (e) {
      var el = e.target.closest('a, button');
      if (!el) return;

      var text = (el.textContent || '').trim().toLowerCase();
      var href = (el.getAttribute('href') || '').toLowerCase();

      var isBokaBtn =
        text === 'boka tid' ||
        href.includes('boka-tid') ||
        href.includes('boka-formular');

      if (isBokaBtn) {
        sendEvent('cta_click_boka_tid', { page: page });
      }
    });
  })();

  // ── 4. email_click ──────────────────────────────────────────────────────
  // Triggas vid klick på mailto-länk för Mools e-postadresser.
  (function () {
    var moolEmails = ['nina@mool.se', 'kp@mool.se', 'wow@mool.se'];

    document.addEventListener('click', function (e) {
      var el = e.target.closest('a[href^="mailto:"]');
      if (!el) return;

      var email = (el.getAttribute('href') || '')
        .replace('mailto:', '')
        .split('?')[0]
        .toLowerCase()
        .trim();

      if (moolEmails.indexOf(email) !== -1) {
        sendEvent('email_click', { email_address: email, page: page });
      }
    });
  })();

})();
