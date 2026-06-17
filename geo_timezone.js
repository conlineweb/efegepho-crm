(function () {
  'use strict';

  function isLocalhost() {
    try {
      var h = window.location && window.location.hostname;
      return h === 'localhost' || h === '127.0.0.1' || h === '::1';
    } catch (e) {
      return false;
    }
  }

  function swalError(title, text) {
    try {
      if (typeof Swal !== 'undefined' && Swal && Swal.fire) {
        return Swal.fire({
          icon: 'error',
          title: title,
          text: text,
          allowOutsideClick: false,
          customClass: { popup: 'swal2-popup-center' }
        });
      }
    } catch (e) {
      // ignore
    }
    alert(title + '\n\n' + text);
  }

  function withTimeout(promise, timeoutMs) {
    return new Promise(function (resolve, reject) {
      var timeoutId = setTimeout(function () {
        reject(new Error('timeout'));
      }, timeoutMs);
      promise.then(function (v) {
        clearTimeout(timeoutId);
        resolve(v);
      }).catch(function (e) {
        clearTimeout(timeoutId);
        reject(e);
      });
    });
  }

  function getCurrentPosition(options) {
    return new Promise(function (resolve, reject) {
      // Geolocation is only allowed in secure contexts (HTTPS) or localhost.
      if (typeof window.isSecureContext !== 'undefined' && !window.isSecureContext && !isLocalhost()) {
        reject(new Error('insecure_context'));
        return;
      }
      if (!navigator.geolocation) {
        reject(new Error('geolocation_not_supported'));
        return;
      }
      navigator.geolocation.getCurrentPosition(resolve, reject, options);
    });
  }

  async function fetchTimezoneByCoords(lat, lon, timeoutMs, timezoneDbApiKey) {
    // Use TimeZoneDB only (requires API key). If no key is provided, fall back to browser timezone
    try {
      var tzDbKey = timezoneDbApiKey || window.TIMEZONEDB_API_KEY || null;
      if (tzDbKey) {
        var tzdbUrl = 'https://api.timezonedb.com/v2.1/get-time-zone?key=' + encodeURIComponent(tzDbKey) + '&format=json&by=position&lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lon);
        var resp = await withTimeout(fetch(tzdbUrl, { cache: 'no-store' }), timeoutMs);
        if (resp.ok) {
          var data = await resp.json();
          if (data && data.zoneName) {
            return data.zoneName; // e.g. 'America/Mexico_City'
          }
        } else {
          console.warn('TimeZoneDB respondió con código:', resp.status);
        }
      } else {
        console.warn('No TimeZoneDB API key provided; using browser fallback which may be less precise.');
      }
    } catch (e) {
      console.warn('TimeZoneDB falló, usando fallback:', e);
    }

    // Fallback using browser timezone
    try {
      var browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      if (browserTz) {
        console.warn('Usando timezone del navegador como fallback:', browserTz);
        return browserTz;
      }
    } catch (e) {
      // Continue
    }

    // Mapeo manual básico para México
    if (lat >= 14.5 && lat <= 32.7 && lon >= -118.4 && lon <= -86.7) {
      if (lon > -106) return 'America/Mexico_City';
      if (lon > -112) return 'America/Chihuahua';
      if (lon > -115) return 'America/Mazatlan';
      return 'America/Tijuana';
    }

    throw new Error('timezone_api_no_timezone');
  }

  function computeOffsetMinutesForZone(timeZone) {
    // Uses browser ICU tzdata; NOT the device timezone setting.
    // Technique: format now in target TZ, parse as if local, diff from now.
    var now = new Date();
    var parts = new Intl.DateTimeFormat('en-US', {
      timeZone: timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    }).formatToParts(now);

    function p(type) {
      return parts.find(function (x) { return x.type === type; }).value;
    }

    var asIfLocal = new Date(
      p('year') + '-' + p('month') + '-' + p('day') + 'T' + p('hour') + ':' + p('minute') + ':' + p('second')
    );

    // Offset minutes = UTC - local. We want hours like -6 for UTC-6.
    // Diff between "asIfLocal" (target zone clock) and now (local clock) gives shift.
    var diffMs = asIfLocal.getTime() - now.getTime();
    // Convert shift to minutes, then adjust by local offset to get absolute offset for target zone.
    var localOffsetMin = now.getTimezoneOffset();
    var targetOffsetMin = localOffsetMin - Math.round(diffMs / 60000);

    // Validate range (-14..+14 hours)
    if (targetOffsetMin < -14 * 60 || targetOffsetMin > 14 * 60) {
      return null;
    }
    return targetOffsetMin;
  }

  function getBrowserTimezoneResult() {
    var tzName = 'America/Mexico_City';
    try {
      var browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      if (browserTz) tzName = browserTz;
    } catch (e) { /* use default */ }
    var offsetMin = computeOffsetMinutesForZone(tzName);
    var offsetHours = offsetMin === null ? null : -offsetMin / 60;
    return {
      timezone_name: tzName,
      timezone_offset_min: offsetMin,
      timezone_offset_hours: offsetHours,
      latitude: null,
      longitude: null
    };
  }

  window.requireConfirmedTimezone = async function requireConfirmedTimezone(opts) {
    opts = opts || {};

    // Cache so multiple calls don't reprompt.
    if (window.__confirmedTimezone && window.__confirmedTimezone.timezone_name) {
      return window.__confirmedTimezone;
    }
    if (window.__confirmedTimezonePromise) {
      return window.__confirmedTimezonePromise;
    }

    window.__confirmedTimezonePromise = (async function () {
      var geoTimeoutMs = typeof opts.geoTimeoutMs === 'number' ? opts.geoTimeoutMs : 8000;
      var apiTimeoutMs = typeof opts.apiTimeoutMs === 'number' ? opts.apiTimeoutMs : 8000;

      // Try geolocation silently; fall back to browser timezone if unavailable or denied.
      var lat = null, lon = null;
      try {
        var position = await withTimeout(
          getCurrentPosition({ enableHighAccuracy: false, timeout: geoTimeoutMs, maximumAge: 5 * 60 * 1000 }),
          geoTimeoutMs + 2000
        );
        lat = position.coords.latitude;
        lon = position.coords.longitude;
      } catch (e) {
        console.warn('Geolocalización no disponible, usando timezone del navegador:', e && e.message);
      }

      var tzName;
      if (lat !== null && lon !== null) {
        try {
          var timezoneDbApiKey = "4LDEAHVOBN5M";
          tzName = await fetchTimezoneByCoords(lat, lon, apiTimeoutMs, timezoneDbApiKey);
        } catch (e) {
          console.warn('API timezone falló, usando fallback del navegador:', e && e.message);
        }
      }

      if (!tzName) {
        var fallback = getBrowserTimezoneResult();
        window.__confirmedTimezone = fallback;
        return fallback;
      }

      var offsetMin = computeOffsetMinutesForZone(tzName);
      var offsetHours = offsetMin === null ? null : -offsetMin / 60;
      var result = {
        timezone_name: tzName,
        timezone_offset_min: offsetMin,
        timezone_offset_hours: offsetHours,
        latitude: lat,
        longitude: lon
      };

      window.__confirmedTimezone = result;
      return result;
    })();

    return window.__confirmedTimezonePromise;
  };

  // Always returns true — geolocation is no longer required to proceed.
  window.blockIfNoTimezone = async function blockIfNoTimezone() {
    try {
      await window.requireConfirmedTimezone();
    } catch (e) {
      console.warn('blockIfNoTimezone: error silenciado:', e && e.message);
    }
    return true;
  };
})();