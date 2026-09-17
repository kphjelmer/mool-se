/**
 * Mool – mikrokonverteringar
 *
 * Pushar events till dataLayer. GTM avgör vad som skickas vidare till GA4 och
 * Google Ads, och konverteringslabels bor i GTM – inte här.
 *
 * Tidigare version anropade gtag() direkt. Det fungerade aldrig: ingen
 * Google-tagg på sajten definierar gtag(), så vakten "typeof gtag" gjorde att
 * alla fyra events tystnade utan felmeddelande.
 *
 * Events: mool_engaged_session, mool_deep_scroll, mool_cta_boka_tid, mool_email_klick
 */
(function () {
  'use strict';

  function sendEvent(name, params) {
    window.dataLayer = window.dataLayer || [];
    var payload = { event: name };
    if (params) {
      Object.keys(params).forEach(function (k) { payload[k] = params[k]; });
    }
    window.dataLayer.push(payload);
  }

  function sessionFlag(key) {
    return {
      isSet: function () {
        try { return sessionStorage.getItem(key) === '1'; } catch (e) { return false; }
      },
      set: function () {
        try { sessionStorage.setItem(key, '1'); } catch (e) {}
      },
    };
  }

  var page = window.location.pathname;

  // ── 1. mool_engaged_session ─────────────────────────────────────────────
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
      sendEvent('mool_engaged_session', { page_path: page });
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

  // ── 2. mool_deep_scroll ─────────────────────────────────────────────────
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
          sendEvent('mool_deep_scroll', { page_path: page });
          window.removeEventListener('scroll', onScroll);
        }
      });
    }

    window.addEventListener('scroll', onScroll, { passive: true });
  })();

  // ── 3. mool_cta_boka_tid ────────────────────────────────────────────────
  // Triggas vid klick på "Boka tid"-knappar och bokningslänkar.
  // Textmatchningen är avsiktligt tolerant: knapptexten på sajten varierar
  // ("Boka tid", "Boka din tid", "BOKA TID NU").
  (function () {
    document.addEventListener('click', function (e) {
      var el = e.target.closest ? e.target.closest('a, button') : null;
      if (!el) return;

      var text = (el.textContent || '').trim().toLowerCase();
      var href = (el.getAttribute('href') || '').toLowerCase();

      var isBokaBtn =
        text.indexOf('boka') === 0 ||
        href.indexOf('boka-tid') !== -1 ||
        href.indexOf('boka-formular') !== -1;

      if (isBokaBtn) {
        sendEvent('mool_cta_boka_tid', { page_path: page, link_text: text.slice(0, 80) });
      }
    });
  })();

  // ── 4. mool_email_klick ─────────────────────────────────────────────────
  // Triggas vid klick på mailto-länk för Mools e-postadresser.
  (function () {
    var moolEmails = ['nina@mool.se', 'kp@mool.se', 'wow@mool.se'];

    document.addEventListener('click', function (e) {
      var el = e.target.closest ? e.target.closest('a[href^="mailto:"]') : null;
      if (!el) return;

      var email = (el.getAttribute('href') || '')
        .replace('mailto:', '')
        .split('?')[0]
        .toLowerCase()
        .trim();

      if (moolEmails.indexOf(email) !== -1) {
        sendEvent('mool_email_klick', { email_address: email, page_path: page });
      }
    });
  })();

})();
