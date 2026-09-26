/*
 * TeachLuma Games — shared "fun layer": confetti, floating score pops,
 * answer shake/bounce, question entrance animation, 1-3 star results,
 * personal-best tracking and two-team classroom play.
 *
 * Every game loads this with a plain <script src="../shared/game-fx.js">
 * and only ever calls it through a `window.TLFX` guard, so a game still
 * plays normally (just without the extras) if this file fails to load.
 * No dependencies, no build step; styles are injected on load.
 */
(function () {
  'use strict';

  var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  var CSS = [
    '@keyframes tlfxShake{0%,100%{transform:translateX(0)}20%{transform:translateX(-8px)}40%{transform:translateX(8px)}60%{transform:translateX(-5px)}80%{transform:translateX(5px)}}',
    '@keyframes tlfxBounce{0%{transform:scale(1)}40%{transform:scale(1.12)}70%{transform:scale(.96)}100%{transform:scale(1)}}',
    '@keyframes tlfxEnter{0%{opacity:0;transform:translateY(14px) scale(.97)}100%{opacity:1;transform:none}}',
    '@keyframes tlfxPop{0%{opacity:0;transform:translate(-50%,0) scale(.6)}20%{opacity:1;transform:translate(-50%,-14px) scale(1.15)}100%{opacity:0;transform:translate(-50%,-70px) scale(1)}}',
    '@keyframes tlfxStar{0%{opacity:0;transform:scale(.2) rotate(-40deg)}60%{opacity:1;transform:scale(1.3) rotate(8deg)}100%{opacity:1;transform:scale(1) rotate(0)}}',
    '@keyframes tlfxTurn{0%{transform:scale(.85);opacity:.4}100%{transform:scale(1);opacity:1}}',
    '.tlfx-shake{animation:tlfxShake .45s ease}',
    '.tlfx-bounce{animation:tlfxBounce .45s ease}',
    '.tlfx-enter{animation:tlfxEnter .35s ease-out both}',
    '.tlfx-pop{position:fixed;z-index:10000;pointer-events:none;font:800 clamp(1.4rem,4vw,2.2rem)/1 "Segoe UI",Roboto,Arial,sans-serif;text-shadow:0 2px 0 #fff,0 0 12px rgba(255,255,255,.9);animation:tlfxPop .9s ease-out forwards;white-space:nowrap}',
    '.tlfx-extras{display:flex;flex-direction:column;align-items:center;gap:.35rem;margin:0 0 1.25rem}',
    '.tlfx-stars{display:flex;gap:.4rem;font-size:clamp(2rem,7vw,3rem);line-height:1}',
    '.tlfx-star{opacity:0;filter:grayscale(1);}',
    '.tlfx-star.on{filter:none;animation:tlfxStar .5s ease-out forwards}',
    '.tlfx-star.off{opacity:.25}',
    '.tlfx-best{font:700 clamp(.95rem,2.2vw,1.1rem)/1.3 "Segoe UI",Roboto,Arial,sans-serif;color:#64748b}',
    '.tlfx-best.new{color:#b45309;background:#fef3c7;border:2px solid #f59e0b;border-radius:999px;padding:.3rem .9rem;animation:tlfxBounce .6s ease .9s both}',
    '.tlfx-team-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .8rem;border-radius:999px;color:#fff;font-weight:800;white-space:nowrap;animation:tlfxTurn .3s ease-out}',
    '.tlfx-team-0{background:#d97706}',
    '.tlfx-team-1{background:#2563eb}',
    '.start-actions{display:grid;grid-template-columns:2fr 1fr;gap:.6rem}',
    '.start-actions .start-btn{margin:0}',
    '.start-btn.start-btn-teams{background:#fff;color:var(--tlfx-accent,#2563eb);border:2.5px solid var(--tlfx-accent,#2563eb)}',
    '.start-btn.start-btn-teams:hover{background:var(--tlfx-accent,#2563eb);color:#fff}',
    '@media (max-width:420px){.start-actions{grid-template-columns:1fr 1fr}}',
    '@media (prefers-reduced-motion:reduce){.tlfx-shake,.tlfx-bounce,.tlfx-enter,.tlfx-team-pill{animation:none}.tlfx-star.on{animation:none;opacity:1}.tlfx-pop{animation-duration:.01s}}'
  ].join('\n');

  function injectCss() {
    if (document.getElementById('tlfx-styles')) { return; }
    var style = document.createElement('style');
    style.id = 'tlfx-styles';
    style.textContent = CSS;
    document.head.appendChild(style);
  }
  injectCss();

  // In fullscreen only the fullscreened element's subtree is painted, so
  // overlays must be attached there rather than to <body>.
  function overlayRoot() {
    return document.fullscreenElement || document.webkitFullscreenElement || document.body;
  }

  function replayClass(el, cls) {
    if (!el) { return; }
    el.classList.remove(cls);
    void el.offsetWidth;
    el.classList.add(cls);
    el.addEventListener('animationend', function done() {
      el.classList.remove(cls);
      el.removeEventListener('animationend', done);
    });
  }

  function shake(el) { replayClass(el, 'tlfx-shake'); }
  function bounce(el) { replayClass(el, 'tlfx-bounce'); }
  function enter(el) { replayClass(el, 'tlfx-enter'); }

  function popText(anchor, text, color) {
    if (!anchor) { return; }
    var r = anchor.getBoundingClientRect();
    var span = document.createElement('span');
    span.className = 'tlfx-pop';
    span.textContent = text;
    span.style.color = color || '#16a34a';
    span.style.left = (r.left + r.width / 2) + 'px';
    span.style.top = (r.top + 4) + 'px';
    overlayRoot().appendChild(span);
    window.setTimeout(function () { span.remove(); }, 950);
  }

  var CONFETTI_COLORS = ['#2563eb', '#7c3aed', '#f59e0b', '#16a34a', '#ec4899', '#0d9488', '#facc15'];

  function confetti(amount) {
    if (reduceMotion) { return; }
    var count = amount || 140;
    var canvas = document.createElement('canvas');
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    var w = window.innerWidth;
    var h = window.innerHeight;
    canvas.width = w * dpr;
    canvas.height = h * dpr;
    canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:9999';
    overlayRoot().appendChild(canvas);
    var ctx = canvas.getContext('2d');
    if (!ctx) { canvas.remove(); return; }
    ctx.scale(dpr, dpr);

    var parts = [];
    for (var i = 0; i < count; i++) {
      var fromLeft = i % 2 === 0;
      parts.push({
        x: fromLeft ? w * 0.15 : w * 0.85,
        y: h * 0.75,
        vx: (fromLeft ? 1 : -1) * (2 + Math.random() * 6),
        vy: -(9 + Math.random() * 8),
        size: 6 + Math.random() * 7,
        rot: Math.random() * Math.PI,
        vr: (Math.random() - 0.5) * 0.4,
        color: CONFETTI_COLORS[i % CONFETTI_COLORS.length],
        round: Math.random() < 0.3
      });
    }

    var start = performance.now();
    var DURATION = 2600;
    function frame(now) {
      var t = now - start;
      ctx.clearRect(0, 0, w, h);
      parts.forEach(function (p) {
        p.vy += 0.28;
        p.vx *= 0.99;
        p.x += p.vx;
        p.y += p.vy;
        p.rot += p.vr;
        ctx.save();
        ctx.globalAlpha = Math.max(0, 1 - t / DURATION);
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rot);
        ctx.fillStyle = p.color;
        if (p.round) {
          ctx.beginPath();
          ctx.arc(0, 0, p.size / 2, 0, Math.PI * 2);
          ctx.fill();
        } else {
          ctx.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2);
        }
        ctx.restore();
      });
      if (t < DURATION) {
        window.requestAnimationFrame(frame);
      } else {
        canvas.remove();
      }
    }
    window.requestAnimationFrame(frame);
  }

  function starCount(pct) {
    if (pct >= 90) { return 3; }
    if (pct >= 60) { return 2; }
    return 1;
  }

  // Personal best is stored per browser (a classroom device), keyed by
  // game + settings, as a single "higher is better" number.
  function recordBest(key, value) {
    var storageKey = 'tl_best_' + key;
    var prev = null;
    try {
      var raw = localStorage.getItem(storageKey);
      prev = raw === null ? null : parseFloat(raw);
    } catch (e) { return { best: value, isNew: false, prev: null }; }
    var isNew = prev === null || value > prev;
    if (isNew) {
      try { localStorage.setItem(storageKey, String(value)); } catch (e) { /* non-fatal */ }
    }
    return { best: isNew ? value : prev, isNew: isNew && prev !== null, first: prev === null };
  }

  /*
   * Results-screen extras. opts:
   *   after      element to insert the stars/best line after
   *   stars      1-3, or 0/undefined to skip
   *   bestKey    localStorage key suffix, or null to skip personal best
   *   bestValue  number to compare (higher = better)
   *   bestLabel  function(value) -> display text for the stored best
   *   confetti   true to celebrate
   */
  function celebrate(opts) {
    var after = opts.after;
    if (!after || !after.parentNode) { return; }
    var existing = after.parentNode.querySelector('.tlfx-extras');
    if (existing) { existing.remove(); }

    var box = document.createElement('div');
    box.className = 'tlfx-extras';

    if (opts.stars) {
      var starsEl = document.createElement('div');
      starsEl.className = 'tlfx-stars';
      starsEl.setAttribute('aria-label', opts.stars + ' out of 3 stars');
      for (var i = 0; i < 3; i++) {
        var s = document.createElement('span');
        s.className = 'tlfx-star ' + (i < opts.stars ? 'on' : 'off');
        s.textContent = '⭐';
        if (i < opts.stars) { s.style.animationDelay = (0.15 + i * 0.22) + 's'; }
        starsEl.appendChild(s);
      }
      box.appendChild(starsEl);
    }

    if (opts.bestKey) {
      var res = recordBest(opts.bestKey, opts.bestValue);
      var bestEl = document.createElement('p');
      bestEl.style.margin = '0';
      var label = opts.bestLabel || function (v) { return String(v); };
      if (res.isNew) {
        bestEl.className = 'tlfx-best new';
        bestEl.textContent = '🏅 New personal best!';
      } else {
        bestEl.className = 'tlfx-best';
        bestEl.textContent = res.first ? 'Personal best saved: ' + label(res.best) : 'Personal best: ' + label(res.best);
      }
      box.appendChild(bestEl);
    }

    after.parentNode.insertBefore(box, after.nextSibling);

    if (opts.confetti) {
      window.setTimeout(function () { confetti(opts.stars === 3 ? 200 : 120); }, 250);
    }
  }

  // -------------------------------------------------------------------
  // Two-team classroom play: teams alternate questions (even = team 0,
  // odd = team 1) and each correct answer scores for the team whose turn
  // it is.
  // -------------------------------------------------------------------
  var TEAM_NAMES = ['⭐ Stars', '🚀 Rockets'];

  function Teams() {
    this.enabled = false;
    this.scores = [0, 0];
  }
  Teams.prototype.reset = function (enabled) {
    this.enabled = !!enabled;
    this.scores = [0, 0];
  };
  Teams.prototype.turn = function (index) { return index % 2; };
  Teams.prototype.award = function (index) { this.scores[index % 2]++; };
  Teams.prototype.renderTurn = function (el, index) {
    if (!el) { return; }
    if (!this.enabled) { el.classList.add('hidden'); el.innerHTML = ''; return; }
    var t = this.turn(index);
    el.classList.remove('hidden');
    // Both team names are plural, so the possessive is just an apostrophe.
    el.innerHTML = '<span class="tlfx-team-pill tlfx-team-' + t + '">' + TEAM_NAMES[t] + '’ turn</span>';
  };
  Teams.prototype.scoreText = function () {
    return '⭐ ' + this.scores[0] + ' · 🚀 ' + this.scores[1];
  };
  Teams.prototype.summary = function () {
    var a = this.scores[0];
    var b = this.scores[1];
    var detail = TEAM_NAMES[0] + ': ' + a + '   ·   ' + TEAM_NAMES[1] + ': ' + b;
    if (a === b) { return { emoji: '🤝', title: "It's a tie!", detail: detail + '. Great teamwork, everyone!' }; }
    var winner = a > b ? 0 : 1;
    return { emoji: '🏆', title: TEAM_NAMES[winner] + ' win!', detail: detail + '.' };
  };

  window.TLFX = {
    shake: shake,
    bounce: bounce,
    enter: enter,
    popText: popText,
    confetti: confetti,
    starCount: starCount,
    celebrate: celebrate,
    Teams: Teams,
    TEAM_NAMES: TEAM_NAMES
  };
})();
