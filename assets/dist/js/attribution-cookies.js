/**
 * Attribution cookie bridge for MCT Lead Form.
 *
 * This script is plain ES5 vanilla JS with no build step — it is shipped
 * as-is to assets/dist/js/attribution-cookies.js. Do not introduce syntax
 * that needs transpilation (no arrow functions with lexical this, no
 * optional chaining, no const/let outside function scope, etc.).
 *
 * Runs on every page and does two things:
 *
 *  1. Captures attribution query params from window.location.search into
 *     short-lived cookies (`mct_utm_source`, `mct_gclid`, etc.). A visitor's
 *     first paid-ad landing is rarely the form page itself — they usually
 *     arrive on a campaign / home page and click through. Cookies let us
 *     carry attribution across that navigation.
 *
 *  2. If a lead form is present on the page, synchronises the cookie values
 *     into the form's hidden attribution inputs. The PHP template seeds
 *     those inputs from the current request's $_GET and HTTP_REFERER, but
 *     when the page HTML is served from a full-page cache (W3 Total Cache,
 *     WP Rocket, CloudFront) those baked-in values are stale — they reflect
 *     whoever warmed the cache, not the current visitor. Overwriting from
 *     cookies keeps attribution correct regardless of the cache layer.
 */
(function () {
  'use strict';

  /**
   * Read a query parameter by name, transparently handling both normal
   * (`?utm_source=google`) and bracketed-array (`?utm_source[0]=google`
   * or URL-encoded `?utm_source%5B0%5D=google`) shapes — the latter is
   * emitted by some misconfigured ad tracking templates.
   */
  function getParameterByName(name) {
    var url = window.location.href;

    var normalRegex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)');
    var normalResults = normalRegex.exec(url);
    if (normalResults && normalResults[2]) {
      return decodeURIComponent(normalResults[2].replace(/\+/g, ' '));
    }

    var arrayRegex = new RegExp('[?&]' + name + '(?:\\[\\d+\\]|%5B\\d+%5D)(=([^&#]*)|&|#|$)');
    var arrayResults = arrayRegex.exec(url);
    if (arrayResults && arrayResults[2]) {
      return decodeURIComponent(arrayResults[2].replace(/\+/g, ' '));
    }

    return null;
  }

  function getCookie(name) {
    var value = '; ' + document.cookie;
    var parts = value.split('; ' + name + '=');
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
  }

  function setCookie(name, value, hours) {
    var expiry = new Date();
    expiry.setTime(expiry.getTime() + (hours * 60 * 60 * 1000));
    document.cookie = name + '=' + value + '; expires=' + expiry.toUTCString() + '; path=/';
  }

  // Step 1 — write live URL attribution params into mct_* cookies.
  var params = ['gclid', 'fbclid', 'msclkid', 'utm_source', 'utm_campaign', 'utm_term'];
  for (var i = 0; i < params.length; i++) {
    var value = getParameterByName(params[i]);
    if (value) {
      setCookie('mct_' + params[i], value, 1);
    }
  }

  // Step 2 — if a lead form is present, overwrite / inject hidden inputs
  // with the cookie values. Maps form-input-name -> cookie-name.
  var INPUT_TO_COOKIE = {
    source:          'mct_utm_source',
    campaign:        'mct_utm_campaign',
    additional_data: 'mct_utm_term',
    gclid:           'mct_gclid',
    fbclid:          'mct_fbclid',
    msclkid:         'mct_msclkid'
  };

  function syncFormInputs() {
    var containers = document.querySelectorAll('[data-mct-lead-form]');
    if (!containers.length) return;

    for (var c = 0; c < containers.length; c++) {
      var forms = containers[c].querySelectorAll('form[data-mct-stage]');
      for (var f = 0; f < forms.length; f++) {
        var form = forms[f];

        for (var inputName in INPUT_TO_COOKIE) {
          if (!INPUT_TO_COOKIE.hasOwnProperty(inputName)) continue;

          var cookieValue = getCookie(INPUT_TO_COOKIE[inputName]);
          if (!cookieValue) continue;

          var existing = form.querySelector('input[name="' + inputName + '"]');
          if (existing) {
            existing.value = cookieValue;
          } else {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = inputName;
            input.value = cookieValue;
            form.appendChild(input);
          }
        }
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncFormInputs);
  } else {
    syncFormInputs();
  }
})();
