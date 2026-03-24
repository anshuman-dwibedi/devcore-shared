/**
 * DevCore UI — devcore.js
 * Shared JS: Toast, Modal, LivePoller, Charts, API helper, Form utils
 * No emoji. Icons use <i class="dc-icon dc-icon-X"> — defined in _icons.css.
 */

/* ─── API HELPER ─────────────────────────────────────────────── */
const DC = {
  async fetch(url, options = {}) {
    const res  = await fetch(url, {
      headers: { 'Content-Type': 'application/json', ...options.headers },
      ...options,
    });
    const data = await res.json();
    if (!res.ok || data.status === 'error') throw new Error(data.message || 'Request failed');
    return data;
  },
  get:    (url)       => DC.fetch(url),
  post:   (url, body) => DC.fetch(url, { method: 'POST',   body: JSON.stringify(body) }),
  put:    (url, body) => DC.fetch(url, { method: 'PUT',    body: JSON.stringify(body) }),
  delete: (url)       => DC.fetch(url, { method: 'DELETE' }),
};

/* ─── TOAST ──────────────────────────────────────────────────── */
const Toast = (() => {
  let container;
  function getContainer() {
    if (!container) {
      container = document.createElement('div');
      container.className = 'dc-toast-container';
      document.body.appendChild(container);
    }
    return container;
  }

  // Icon classes from _icons.css — change the icon by editing _icons.css only
  const iconClass = {
    success: 'dc-icon-check',
    error:   'dc-icon-x',
    warning: 'dc-icon-alert-triangle',
    info:    'dc-icon-info',
  };

  function show(message, type = 'info', duration = 3500) {
    const toast = document.createElement('div');
    toast.className = `dc-toast dc-toast--${type}`;
    toast.innerHTML = `
      <i class="dc-toast__icon dc-icon dc-icon-lg ${iconClass[type]}"></i>
      <div class="dc-toast__body">
        <div class="dc-toast__title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
        <div class="dc-toast__msg">${message}</div>
      </div>
    `;
    getContainer().appendChild(toast);
    setTimeout(() => {
      toast.classList.add('removing');
      setTimeout(() => toast.remove(), 300);
    }, duration);
    return toast;
  }

  return {
    success: (msg, d) => show(msg, 'success', d),
    error:   (msg, d) => show(msg, 'error',   d),
    warning: (msg, d) => show(msg, 'warning', d),
    info:    (msg, d) => show(msg, 'info',    d),
  };
})();

/* ─── MODAL ──────────────────────────────────────────────────── */
const Modal = (() => {
  function open(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
  }
  function close(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
  }
  document.addEventListener('click', (e) => {
    const closeBtn = e.target.closest('[data-modal-close]');
    if (closeBtn) { close(closeBtn.dataset.modalClose); return; }
    const openBtn = e.target.closest('[data-modal-open]');
    if (openBtn)  { open(openBtn.dataset.modalOpen); return; }
    if (e.target.classList.contains('dc-modal-overlay')) {
      e.target.classList.remove('open');
      document.body.style.overflow = '';
    }
  });
  return { open, close };
})();

/* ─── LIVE POLLER ────────────────────────────────────────────── */
class DCPollTransport {
  constructor(url, interval = 3000) {
    this.url = url;
    this.interval = interval;
    this.timer = null;
    this.abortController = null;
  }

  start(onData, onError) {
    const tick = async () => {
      try {
        this.abortController = new AbortController();
        const res = await fetch(this.url, { signal: this.abortController.signal });
        const data = await res.json();
        if (!res.ok || data?.status === 'error') {
          throw new Error(data?.message || 'Request failed');
        }
        onData(data);
      } catch (err) {
        if (err.name !== 'AbortError') onError(err);
      }
    };

    tick();
    this.timer = setInterval(tick, this.interval);
  }

  stop() {
    if (this.timer) clearInterval(this.timer);
    this.timer = null;
    if (this.abortController) this.abortController.abort();
    this.abortController = null;
  }
}

class DCSSETransport {
  constructor(url, eventName = 'message') {
    this.url = url;
    this.eventName = eventName;
    this.source = null;
  }

  start(onData, onError) {
    this.source = new EventSource(this.url);

    const handleMessage = (evt) => {
      try {
        const payload = JSON.parse(evt.data);
        if (payload?.status === 'error') {
          throw new Error(payload?.message || 'SSE error payload');
        }
        onData(payload);
      } catch (err) {
        onError(err);
      }
    };

    if (this.eventName && this.eventName !== 'message') {
      this.source.addEventListener(this.eventName, handleMessage);
    } else {
      this.source.onmessage = handleMessage;
    }

    this.source.onerror = () => {
      onError(new Error('SSE connection error'));
    };
  }

  stop() {
    if (this.source) this.source.close();
    this.source = null;
  }
}

class LivePoller {
  constructor(url, callback, intervalOrOptions = 3000) {
    this.url = url;
    this.callback = callback;
    this.running = false;
    this.transport = null;

    const baseOptions = {
      mode: 'poll',
      interval: 3000,
      sseUrl: null,
      eventName: 'message',
      transport: null,
    };

    if (typeof intervalOrOptions === 'number') {
      this.options = { ...baseOptions, interval: intervalOrOptions };
    } else {
      this.options = { ...baseOptions, ...(intervalOrOptions || {}) };
    }
  }

  start() {
    if (this.running) return;
    this.running = true;
    this.transport = this._createTransport();
    this.transport.start(
      (data) => this.callback(data),
      (err) => console.warn('[LivePoller]', err.message)
    );
  }

  stop() {
    if (this.transport) this.transport.stop();
    this.transport = null;
    this.running = false;
  }

  _createTransport() {
    if (this.options.transport && typeof this.options.transport.start === 'function') {
      return this.options.transport;
    }

    const mode = this.options.mode;
    if (mode === 'sse' || (mode === 'auto' && typeof EventSource !== 'undefined')) {
      return new DCSSETransport(this.options.sseUrl || this.url, this.options.eventName);
    }

    return new DCPollTransport(this.url, this.options.interval);
  }
}

/* ─── CHARTS (Chart.js wrappers) ─────────────────────────────── */
const DCChart = {
  defaults: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { labels: { color: '#9898b0', font: { family: 'DM Sans', size: 12 } } },
      tooltip: {
        backgroundColor: '#18181f', borderColor: 'rgba(255,255,255,0.08)', borderWidth: 1,
        titleColor: '#f0f0f5', bodyColor: '#9898b0', padding: 12, cornerRadius: 10,
      },
    },
    scales: {
      x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#5a5a72', font: { family: 'DM Sans', size: 11 } } },
      y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#5a5a72', font: { family: 'DM Sans', size: 11 } } },
    },
  },
  line(id, labels, datasets, opts = {}) {
    const ctx = document.getElementById(id); if (!ctx) return null;
    return new Chart(ctx, { type: 'line', data: { labels, datasets: datasets.map(d => ({
      tension: 0.4, fill: true, backgroundColor: 'rgba(108,99,255,0.08)',
      borderColor: '#6c63ff', borderWidth: 2, pointBackgroundColor: '#6c63ff', pointRadius: 4, ...d,
    })) }, options: { ...this.defaults, ...opts } });
  },
  bar(id, labels, datasets, opts = {}) {
    const ctx = document.getElementById(id); if (!ctx) return null;
    return new Chart(ctx, { type: 'bar', data: { labels, datasets: datasets.map(d => ({
      backgroundColor: 'rgba(108,99,255,0.6)', borderColor: '#6c63ff',
      borderWidth: 1, borderRadius: 6, ...d,
    })) }, options: { ...this.defaults, ...opts } });
  },
  doughnut(id, labels, data, opts = {}) {
    const ctx = document.getElementById(id); if (!ctx) return null;
    const colors = ['#6c63ff','#22d3a0','#f5a623','#ff5c6a','#38bdf8','#a78bfa'];
    return new Chart(ctx, { type: 'doughnut',
      data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 0, hoverOffset: 6 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '72%',
        plugins: { ...this.defaults.plugins }, ...opts } });
  },
};

/* ─── FORM HELPER ────────────────────────────────────────────── */
const DCForm = {
  serialize: (form) => Object.fromEntries(new FormData(form).entries()),
  setLoading(btn, loading) {
    if (loading) { btn.dataset.orig = btn.innerHTML; btn.classList.add('loading'); btn.disabled = true; }
    else { btn.innerHTML = btn.dataset.orig || btn.innerHTML; btn.classList.remove('loading'); btn.disabled = false; }
  },
  showErrors(form, errors) {
    DCForm.clearErrors(form);
    Object.entries(errors).forEach(([field, msgs]) => {
      const input = form.querySelector(`[name="${field}"]`);
      if (input) {
        input.classList.add('dc-input-error');
        const err = document.createElement('span');
        err.className = 'dc-error-msg';
        err.textContent = Array.isArray(msgs) ? msgs[0] : msgs;
        input.parentNode.appendChild(err);
      }
    });
  },
  clearErrors(form) {
    form.querySelectorAll('.dc-error-msg').forEach(el => el.remove());
    form.querySelectorAll('.dc-input-error').forEach(el => el.classList.remove('dc-input-error'));
  },
};

/* ─── DATE INPUT COMPONENT ───────────────────────────────────── */
const DCDateInput = {
  mount(input, options = {}) {
    if (!input) return null;

    const config = {
      min: null,
      max: null,
      displayFormat: 'mdy',
      openPickerOnFocus: false,
      onValid: () => {},
      onInvalid: () => {},
      onEmpty: () => {},
      ...options,
    };

    input.classList.add('dc-date-input');

    const wrapper = document.createElement('div');
    wrapper.className = 'dc-date-field';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'dc-date-field__trigger';
    trigger.setAttribute('aria-label', 'Open calendar');
    trigger.innerHTML = '<i class="dc-icon dc-icon-calendar dc-icon-sm"></i>';
    wrapper.appendChild(trigger);

    const nativePicker = document.createElement('input');
    nativePicker.type = 'date';
    nativePicker.className = 'dc-date-field__native';
    if (config.min) nativePicker.min = config.min;
    if (config.max) nativePicker.max = config.max;
    wrapper.appendChild(nativePicker);

    if (!input.placeholder) {
      input.placeholder = config.displayFormat === 'iso' ? 'YYYY-MM-DD' : 'MM/DD/YYYY';
    }
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('maxlength', '10');

    const parseDateOnly = (value) => {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
      const d = new Date(value + 'T00:00:00Z');
      if (Number.isNaN(d.getTime())) return null;
      return d;
    };

    const toISO = (year, month, day) => {
      const y = String(year).padStart(4, '0');
      const m = String(month).padStart(2, '0');
      const d = String(day).padStart(2, '0');
      const iso = `${y}-${m}-${d}`;
      return parseDateOnly(iso) ? iso : null;
    };

    const fromDisplayToISO = (value) => {
      const raw = String(value || '').trim();
      if (!raw) return { iso: '', partial: false };

      const isoMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (isoMatch) {
        const iso = toISO(isoMatch[1], isoMatch[2], isoMatch[3]);
        return { iso: iso || null, partial: false };
      }

      const mdyMatch = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
      if (mdyMatch) {
        const iso = toISO(mdyMatch[3], mdyMatch[1], mdyMatch[2]);
        return { iso: iso || null, partial: false };
      }

      const digits = raw.replace(/\D/g, '');
      return { iso: null, partial: digits.length < 8 };
    };

    const fromISOToDisplay = (iso) => {
      if (!parseDateOnly(iso)) return '';
      if (config.displayFormat === 'iso') return iso;
      const [y, m, d] = iso.split('-');
      return `${m}/${d}/${y}`;
    };

    const normalize = (raw) => String(raw || '')
      .trim()
      .replace(/\s+/g, '')
      .replace(/[^0-9/\-]/g, '');

    const setVisualState = (invalid) => {
      input.classList.toggle('dc-date-input--invalid', !!invalid);
    };

    let lastInvalidKey = null;

    const reportInvalid = (value, reason, strict) => {
      if (!strict) {
        config.onInvalid(value, reason, strict);
        return;
      }
      const key = `${reason}|${value}`;
      if (key === lastInvalidKey) return;
      lastInvalidKey = key;
      config.onInvalid(value, reason, strict);
    };

    const inRange = (value) => {
      const selected = parseDateOnly(value);
      if (!selected) return false;
      const min = config.min ? parseDateOnly(config.min) : null;
      const max = config.max ? parseDateOnly(config.max) : null;
      if (min && selected < min) return false;
      if (max && selected > max) return false;
      return true;
    };

    const validate = (strict) => {
      const value = normalize(input.value);
      if (value !== input.value) input.value = value;

      if (!value) {
        setVisualState(false);
        lastInvalidKey = null;
        nativePicker.value = '';
        config.onEmpty();
        return;
      }

      const parsed = fromDisplayToISO(value);

      if (parsed.partial) {
        setVisualState(false);
        reportInvalid(value, 'partial', strict);
        return;
      }

      if (!parsed.iso || !parseDateOnly(parsed.iso)) {
        setVisualState(strict);
        reportInvalid(value, 'format', strict);
        return;
      }

      if (!inRange(parsed.iso)) {
        setVisualState(strict);
        reportInvalid(parsed.iso, 'range', strict);
        return;
      }

      setVisualState(false);
      lastInvalidKey = null;
      nativePicker.value = parsed.iso;
      config.onValid(parsed.iso);
    };

    const onInput = () => validate(false);
    const onBlur = () => validate(true);
    const onChange = () => {
      if (document.activeElement === input) return;
      validate(true);
    };

    const syncFromPicker = () => {
      if (!nativePicker.value) return;
      input.value = fromISOToDisplay(nativePicker.value);
      validate(true);
      input.focus();
    };

    const openNativePicker = () => {
      const parsed = fromDisplayToISO(input.value);
      if (parsed.iso) nativePicker.value = parsed.iso;
      try {
        if (typeof nativePicker.showPicker === 'function') {
          nativePicker.showPicker();
        } else {
          nativePicker.focus();
          nativePicker.click();
        }
      } catch (_) {
        nativePicker.focus();
      }
    };

    input.addEventListener('input', onInput);
    input.addEventListener('blur', onBlur);
    input.addEventListener('change', onChange);
    nativePicker.addEventListener('change', syncFromPicker);
    trigger.addEventListener('click', openNativePicker);

    if (config.openPickerOnFocus) {
      input.addEventListener('focus', openNativePicker);
    }

    return {
      validate: (strict = true) => validate(strict),
      getValue: () => {
        const parsed = fromDisplayToISO(normalize(input.value));
        return parsed.iso || '';
      },
      setValue: (value, strict = false) => {
        input.value = fromISOToDisplay(value);
        nativePicker.value = parseDateOnly(value) ? value : '';
        validate(strict);
      },
      destroy: () => {
        input.removeEventListener('input', onInput);
        input.removeEventListener('blur', onBlur);
        input.removeEventListener('change', onChange);
        nativePicker.removeEventListener('change', syncFromPicker);
        trigger.removeEventListener('click', openNativePicker);
        if (config.openPickerOnFocus) {
          input.removeEventListener('focus', openNativePicker);
        }
        wrapper.parentNode.insertBefore(input, wrapper);
        wrapper.remove();
      },
    };
  },
};

/* ─── FORMAT HELPERS ─────────────────────────────────────────── */
const DCFormat = {
  currency: (n, s = '$') => `${s}${Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
  number:   (n) => Number(n).toLocaleString('en-US'),
  percent:  (n) => `${Number(n).toFixed(1)}%`,
  date:     (d) => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
  time:     (d) => new Date(d).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }),
  datetime: (d) => `${DCFormat.date(d)}, ${DCFormat.time(d)}`,
  ago(d) {
    const s = Math.floor((Date.now() - new Date(d)) / 1000);
    if (s < 60) return `${s}s ago`;
    if (s < 3600) return `${Math.floor(s/60)}m ago`;
    if (s < 86400) return `${Math.floor(s/3600)}h ago`;
    return `${Math.floor(s/86400)}d ago`;
  },
};

/* ─── STAT COUNTER ANIMATION ─────────────────────────────────── */
function animateCount(el, target, duration = 1000) {
  const start = parseInt(el.textContent.replace(/\D/g,'')) || 0;
  const t0    = performance.now();
  const tick  = (now) => {
    const p = Math.min((now - t0) / duration, 1);
    el.textContent = DCFormat.number(Math.round(start + (target - start) * (1 - Math.pow(1 - p, 3))));
    if (p < 1) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
}

/* ─── INIT ───────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  const obs = new IntersectionObserver((entries) => {
    entries.forEach(({ isIntersecting, target }) => {
      if (!isIntersecting) return;
      const v = parseInt(target.dataset.count || target.textContent.replace(/\D/g,''));
      if (!isNaN(v)) animateCount(target, v);
      obs.unobserve(target);
    });
  }, { threshold: 0.3 });
  document.querySelectorAll('.dc-stat__value[data-count]').forEach(el => obs.observe(el));
});
