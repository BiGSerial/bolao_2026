<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>BolãoFC — Dispute com seus amigos · Copa do Mundo 2026</title>
<meta name="description" content="Palpites esportivos recreativos entre amigos. Sem apostas, sem dinheiro e sem prêmios em dinheiro. Não somos BET nem casa de apostas.">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
<link rel="shortcut icon" href="{{ asset('favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
<script>
(() => {
  const hasConsent = document.cookie.split('; ').some((cookie) => cookie.startsWith('cookie_consent=accepted'));
  if (!hasConsent) {
    document.documentElement.classList.add('cookie-consent-locked');
  }
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,700;0,800;0,900;1,800&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
/* ── LANDING TOKENS (mockup colors) ── */
:root {
  --lp-bg:     #080a0d;
  --lp-bg2:    #0f1218;
  --lp-bg3:    #161b24;
  --lp-bg4:    #1e2533;
  --lp-border: rgba(255,255,255,0.06);
  --lp-border2:rgba(255,255,255,0.12);
  --lp-text:   #f1f5f9; /* slate-100 */
  --lp-muted:  #64748b; /* slate-500 */
  --lp-muted2: #334155; /* slate-700 */
  --lp-accent: #f5a623; /* amber brand */
  --lp-accent2:#e8390d; /* amber-red gradient */
  --lp-gold:   #fbbf24; /* gold — rankings/podium */
  --lp-red:    #ef4444;
  --lp-blue:   #3b82f6;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body.landing-page {
  font-family: 'Barlow', ui-sans-serif, system-ui, sans-serif;
  background: var(--lp-bg);
  color: var(--lp-text);
  overflow-x: hidden;
}

/* ── NOISE ── */
body.landing-page::before {
  content: '';
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='1'/%3E%3C/svg%3E");
  opacity: 0.02;
}

/* ── NAVBAR ── */
.lp-nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  padding: 0 5vw; height: 64px;
  display: flex; align-items: center; justify-content: space-between;
  border-bottom: 1px solid transparent;
  transition: background .3s, border-color .3s, backdrop-filter .3s;
}
.lp-nav.scrolled {
  background: rgba(8,10,13,0.90);
  border-color: var(--lp-border);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}
.lp-logo { color: var(--lp-text); text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
.lp-logo-img { height: 40px; width: auto; display: block; }
.lp-logo-text { font-weight: 900; font-size: 24px; letter-spacing: -0.5px; color: var(--lp-text); line-height: 1; }
.lp-logo-text span { color: var(--lp-accent); }
.lp-nav-links { display: flex; align-items: center; gap: 6px; }
.lp-nav-link {
  font-size: 14px; font-weight: 500; color: var(--lp-muted);
  text-decoration: none; padding: 8px 14px; border-radius: 8px;
  transition: all .15s; border: none; background: none; cursor: pointer;
}
.lp-nav-link:hover { color: var(--lp-text); background: rgba(255,255,255,0.05); }
.lp-nav-cta {
  font-weight: 700; font-size: 14px;
  background: var(--lp-accent); color: #000;
  border: none; padding: 9px 20px; border-radius: 8px;
  cursor: pointer; transition: all .15s; text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px;
}
.lp-nav-cta:hover { background: #f5a623; transform: translateY(-1px); }

/* ── HERO ── */
.lp-hero {
  min-height: 100vh;
  display: flex; flex-direction: column; justify-content: center;
  padding: 120px 5vw 80px; position: relative; overflow: hidden;
}
.hero-bg-grid {
  position: absolute; inset: 0; z-index: 0;
  background-image:
    linear-gradient(rgba(245,166,35,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(245,166,35,0.03) 1px, transparent 1px);
  background-size: 60px 60px;
  mask-image: radial-gradient(ellipse 80% 70% at 50% 50%, black 40%, transparent 100%);
}
.hero-orb {
  position: absolute; top: -20%; right: -10%;
  width: 700px; height: 700px; border-radius: 50%;
  background: radial-gradient(circle, rgba(245,166,35,0.07) 0%, rgba(232,57,13,0.03) 40%, transparent 70%);
  pointer-events: none; z-index: 0;
}
.hero-orb2 {
  position: absolute; bottom: -30%; left: -15%;
  width: 600px; height: 600px; border-radius: 50%;
  background: radial-gradient(circle, rgba(59,130,246,0.04) 0%, transparent 70%);
  pointer-events: none; z-index: 0;
}
.hero-inner { position: relative; z-index: 1; max-width: 1200px; margin: 0 auto; width: 100%; }

.hero-eyebrow {
  display: inline-flex; align-items: center; gap: 8px;
  font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
  color: var(--lp-accent); background: rgba(245,166,35,0.1);
  border: 1px solid rgba(245,166,35,0.2);
  padding: 5px 14px; border-radius: 99px; margin-bottom: 28px;
  opacity: 0; animation: lp-fadeUp .6s .1s ease forwards;
}
.eyebrow-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--lp-accent); animation: lp-pulse 2s infinite; }

.hero-title {
  font-weight: 900; font-size: clamp(52px, 8.5vw, 120px);
  line-height: .75; letter-spacing: -2px;
  opacity: 0; animation: lp-fadeUp .7s .2s ease forwards;
}
.hero-title em { font-style: italic; color: var(--lp-accent); text-shadow: 0 0 60px rgba(245,166,35,0.3); }

.hero-sub {
  font-size: clamp(15px, 1.8vw, 18px); font-weight: 300;
  color: var(--lp-muted); line-height: 1.7;
  max-width: 500px; margin-top: 24px;
  opacity: 0; animation: lp-fadeUp .7s .35s ease forwards;
}
.hero-sub strong { color: var(--lp-text); font-weight: 500; }

.hero-actions {
  display: flex; align-items: center; gap: 12px; margin-top: 40px; flex-wrap: wrap;
  opacity: 0; animation: lp-fadeUp .7s .48s ease forwards;
}
.lp-btn-primary {
  font-weight: 800; font-size: 16px;
  background: linear-gradient(135deg, var(--lp-accent), #e8390d);
  color: #000; border: none; padding: 15px 32px; border-radius: 10px;
  cursor: pointer; transition: all .2s;
  box-shadow: 0 8px 28px rgba(245,166,35,0.25);
  position: relative; overflow: hidden; text-decoration: none;
  display: inline-flex; align-items: center; gap: 8px;
}
.lp-btn-primary::after {
  content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
  transition: left .4s;
}
.lp-btn-primary:hover::after { left: 100%; }
.lp-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 36px rgba(245,166,35,0.35); }
.lp-btn-ghost {
  font-size: 14px; font-weight: 500; color: var(--lp-muted);
  background: none; border: 1px solid var(--lp-border2);
  padding: 14px 24px; border-radius: 10px;
  cursor: pointer; transition: all .2s;
  display: inline-flex; align-items: center; gap: 8px;
}
.lp-btn-ghost:hover { color: var(--lp-text); border-color: rgba(255,255,255,0.25); background: rgba(255,255,255,0.04); }

.hero-social-proof {
  display: flex; align-items: center; gap: 16px; margin-top: 48px;
  opacity: 0; animation: lp-fadeUp .7s .6s ease forwards;
}
.sp-avatars { display: flex; }
.sp-av {
  width: 30px; height: 30px; border-radius: 50%;
  border: 2px solid var(--lp-bg); margin-left: -8px;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 10px;
}
.sp-av:first-child { margin-left: 0; }
.sp-text { font-size: 13px; color: var(--lp-muted); line-height: 1.4; }
.sp-text strong { color: var(--lp-text); font-weight: 600; }

/* ── HERO MOCKUP ── */
.hero-mockup {
  position: absolute; right: 4vw; top: 50%; transform: translateY(-50%);
  width: min(400px, 36vw); z-index: 1;
  opacity: 0; animation: lp-fadeLeft .8s .5s ease forwards;
}
@media (max-width: 900px) { .hero-mockup { display: none; } }

.mockup-card {
  background: var(--lp-bg3); border: 1px solid var(--lp-border2);
  border-radius: 14px; overflow: hidden; position: relative;
}
.mockup-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, var(--lp-accent), var(--lp-accent2));
}
.mockup-header {
  padding: 12px 16px; border-bottom: 1px solid var(--lp-border);
  display: flex; align-items: center; justify-content: space-between;
}
.mockup-badge {
  font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
  color: var(--lp-accent); background: rgba(245,166,35,0.1); padding: 3px 8px; border-radius: 4px;
}
.mockup-live { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--lp-muted); }
.live-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--lp-red); animation: lp-pulse 1.5s infinite; }

.mockup-match {
  padding: 18px 16px;
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
}
.mk-team { text-align: center; flex: 1; }
.mk-flag { font-size: 32px; display: block; margin-bottom: 6px; }
.mk-crest {
  width: 34px; height: 34px; object-fit: contain; display: block;
  margin: 0 auto 6px;
}
.mk-name { font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: .5px; }
.mk-rank { font-size: 10px; color: var(--lp-muted); margin-top: 2px; }
.mk-score { font-weight: 900; font-size: 46px; letter-spacing: -1px; line-height: 1; }
.mk-score-label { font-size: 10px; color: var(--lp-muted); text-align: center; margin-top: 3px; }

.mockup-guess {
  border-top: 1px solid var(--lp-border); padding: 12px 16px;
  display: flex; align-items: center; gap: 8px;
}
.mk-guess-label { flex: 1; font-size: 11px; color: var(--lp-muted); }
.mk-guess-label b { color: var(--lp-text); display: block; font-size: 12px; margin-bottom: 1px; }
.mk-inputs { display: flex; align-items: center; gap: 5px; }
.mk-inp {
  width: 34px; height: 34px; background: var(--lp-bg4);
  border: 1.5px solid var(--lp-border2); border-radius: 7px;
  font-weight: 700; font-size: 18px;
  color: var(--lp-accent); display: flex; align-items: center; justify-content: center;
}
.mk-sep { font-size: 14px; color: var(--lp-muted2); font-weight: 700; }
.mk-submit-btn {
  background: var(--lp-accent); color: #fff; border: none;
  padding: 0 12px; height: 34px; border-radius: 7px;
  font-weight: 700; font-size: 12px; text-transform: uppercase; cursor: pointer;
}

.mockup-ranking { border-top: 1px solid var(--lp-border); }
.mkr-header { padding: 8px 16px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--lp-muted); }
.mkr-row {
  display: flex; align-items: center; gap: 8px; padding: 7px 16px;
  border-top: 1px solid var(--lp-border);
}
.mkr-row.me { background: rgba(245,166,35,0.05); }
.mkr-pos { font-weight: 800; font-size: 14px; width: 16px; color: var(--lp-muted); }
.mkr-av { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 10px; }
.mkr-name { flex: 1; font-size: 12px; font-weight: 500; }
.mkr-pts { font-weight: 800; font-size: 14px; color: var(--lp-accent); }

.float-card {
  position: absolute; background: var(--lp-bg4);
  border: 1px solid var(--lp-border2); border-radius: 10px;
  padding: 10px 14px; white-space: nowrap;
  animation: lp-float 4s ease-in-out infinite;
}
.float-card:nth-child(2) { animation-delay: -2s; }
.float-1 { top: -36px; right: 24px; }
.float-2 { bottom: 50px; left: -44px; }
.fc-label { font-size: 9px; color: var(--lp-muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 3px; }
.fc-val { font-weight: 800; font-size: 20px; color: #f5a623; }
.fc-val.gold { color: var(--lp-gold); }

/* ── ANIMATIONS ── */
@keyframes lp-fadeUp   { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
@keyframes lp-fadeLeft { from{opacity:0;transform:translate(36px,-50%)} to{opacity:1;transform:translate(0,-50%)} }
@keyframes lp-float    { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-7px)} }
@keyframes lp-pulse    { 0%,100%{opacity:1} 50%{opacity:.3} }

/* ── STATS BAR ── */
.lp-stats-bar {
  background: var(--lp-bg2); border-top: 1px solid var(--lp-border); border-bottom: 1px solid var(--lp-border);
  padding: 0 5vw;
}
.lp-stats-inner {
  max-width: 1200px; margin: 0 auto;
  display: grid; grid-template-columns: repeat(4, 1fr);
}
@media (max-width: 600px) { .lp-stats-inner { grid-template-columns: repeat(2, 1fr); } }
.lp-stat-item {
  padding: 28px 20px; text-align: center;
  border-right: 1px solid var(--lp-border);
}
.lp-stat-item:last-child { border-right: none; }
@media (max-width: 600px) {
  .lp-stat-item:nth-child(2) { border-right: none; }
  .lp-stat-item:nth-child(3), .lp-stat-item:nth-child(4) { border-top: 1px solid var(--lp-border); }
}
.lp-stat-num { font-weight: 900; font-size: clamp(32px, 4.5vw, 52px); line-height: 1; color: var(--lp-accent); }
.lp-stat-desc { font-size: 12px; color: var(--lp-muted); margin-top: 6px; }

/* ── SECTIONS ── */
.lp-section { padding: 96px 5vw; position: relative; }
.lp-section-inner { max-width: 1200px; margin: 0 auto; }
.lp-section-alt { background: var(--lp-bg2); }

.lp-section-label {
  font-size: 11px; font-weight: 700; letter-spacing: 3px;
  text-transform: uppercase; color: var(--lp-accent);
  margin-bottom: 12px; display: block;
}
.lp-section-title {
  font-weight: 900; font-size: clamp(34px, 4.5vw, 64px);
  line-height: .95; letter-spacing: -1px;
}
.lp-section-title em { font-style: italic; color: var(--lp-accent); }
.lp-section-sub {
  font-size: 16px; color: var(--lp-muted); line-height: 1.7;
  max-width: 520px; margin-top: 18px; font-weight: 300;
}

/* ── HOW IT WORKS ── */
.lp-how-grid {
  display: grid; grid-template-columns: repeat(3, 1fr);
  margin-top: 56px; border: 1px solid var(--lp-border); border-radius: 14px; overflow: hidden;
}
@media (max-width: 700px) { .lp-how-grid { grid-template-columns: 1fr; } }
.lp-how-card {
  background: var(--lp-bg2); padding: 36px 28px;
  border-right: 1px solid var(--lp-border);
  transition: background .2s; position: relative; overflow: hidden;
}
.lp-how-card:last-child { border-right: none; }
.lp-how-card::after {
  content: '';
  position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, var(--lp-accent), transparent);
  opacity: 0; transition: opacity .2s;
}
.lp-how-card:hover { background: var(--lp-bg3); }
.lp-how-card:hover::after { opacity: 1; }
.lp-how-num {
  font-weight: 900; font-size: 72px; line-height: 1;
  color: rgba(245,166,35,0.07); letter-spacing: -2px;
  margin-bottom: -8px; display: block;
}
.lp-how-icon { font-size: 28px; margin-bottom: 14px; display: block; }
.lp-how-title { font-weight: 800; font-size: 20px; letter-spacing: .3px; margin-bottom: 10px; }
.lp-how-desc { font-size: 14px; color: var(--lp-muted); line-height: 1.6; }

/* ── FEATURES ── */
.lp-features-header {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 40px; flex-wrap: wrap; margin-bottom: 48px;
}
.lp-features-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
@media (max-width: 700px) { .lp-features-grid { grid-template-columns: 1fr; } }
.lp-feature-card {
  background: var(--lp-bg3); border: 1px solid var(--lp-border);
  border-radius: 12px; padding: 26px 26px 30px;
  transition: all .2s; position: relative; overflow: hidden;
}
.lp-feature-card:hover { border-color: var(--lp-border2); transform: translateY(-3px); box-shadow: 0 20px 50px rgba(0,0,0,0.4); }
.lp-feature-card.featured { border-color: rgba(245,166,35,0.2); background: linear-gradient(135deg, rgba(245,166,35,0.04), var(--lp-bg3)); }
.lp-fc-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 18px; }
.lp-fc-icon.green { background: rgba(245,166,35,0.12); }
.lp-fc-icon.blue  { background: rgba(59,130,246,0.12); }
.lp-fc-icon.gold  { background: rgba(245,158,11,0.12); }
.lp-fc-icon.purple{ background: rgba(168,85,247,0.12); }
.lp-fc-title { font-weight: 800; font-size: 20px; letter-spacing: .3px; margin-bottom: 8px; }
.lp-fc-desc { font-size: 14px; color: var(--lp-muted); line-height: 1.65; }
.lp-fc-badge {
  display: inline-block; margin-top: 14px;
  font-size: 10px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase;
  padding: 3px 10px; border-radius: 4px;
}
.lp-fc-badge.green { background: rgba(245,166,35,0.12); color: var(--lp-accent); }
.lp-fc-badge.blue  { background: rgba(59,130,246,0.12); color: var(--lp-blue); }

/* ── SCORING ── */
.lp-scoring-layout {
  display: grid; grid-template-columns: 1fr 1fr; gap: 72px; align-items: center;
}
@media (max-width: 800px) { .lp-scoring-layout { grid-template-columns: 1fr; gap: 40px; } }
.lp-scoring-table { display: flex; flex-direction: column; gap: 3px; margin-top: 36px; }
.lp-score-row {
  display: flex; align-items: center; gap: 14px;
  padding: 16px 18px; border-radius: 10px;
  background: var(--lp-bg2); border: 1px solid var(--lp-border);
  transition: all .15s;
}
.lp-score-row:hover { border-color: var(--lp-border2); transform: translateX(4px); }
.lp-score-icon { font-size: 20px; width: 32px; text-align: center; }
.lp-score-info { flex: 1; }
.lp-score-name { font-weight: 600; font-size: 14px; margin-bottom: 2px; }
.lp-score-sub  { font-size: 11px; color: var(--lp-muted); }
.lp-score-pts  { font-weight: 900; font-size: 26px; letter-spacing: -1px; }
.lp-score-pts.g { color: #f5a623; }
.lp-score-pts.b { color: var(--lp-blue); }
.lp-score-pts.r { color: var(--lp-muted2); }

.lp-ranking-preview {
  background: var(--lp-bg2); border: 1px solid var(--lp-border2);
  border-radius: 14px; overflow: hidden;
}
.lp-rp-header {
  padding: 14px 18px; border-bottom: 1px solid var(--lp-border);
  display: flex; align-items: center; justify-content: space-between;
}
.lp-rp-title { font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: .5px; }
.lp-rp-badge { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--lp-accent); background: rgba(245,166,35,.1); padding: 3px 8px; border-radius: 4px; }
.lp-rp-row {
  display: flex; align-items: center; gap: 10px;
  padding: 11px 18px; border-bottom: 1px solid var(--lp-border); transition: background .15s;
}
.lp-rp-row:last-child { border-bottom: none; }
.lp-rp-row.you { background: rgba(245,166,35,0.05); }
.lp-rp-pos { font-weight: 900; font-size: 15px; width: 20px; color: var(--lp-muted); }
.lp-rp-av { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; }
.lp-rp-info { flex: 1; }
.lp-rp-name   { font-size: 13px; font-weight: 500; }
.lp-rp-detail { font-size: 11px; color: var(--lp-muted); margin-top: 1px; }
.lp-rp-pts    { font-weight: 800; font-size: 18px; color: var(--lp-accent); }
.trend-up { color: #f5a623; font-size: 12px; }
.trend-dn { color: #ef4444; font-size: 12px; }
.trend-eq { color: var(--lp-muted2); font-size: 12px; }

/* ── TESTIMONIALS ── */
.lp-testimonials { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-top: 48px; }
@media (max-width: 700px) { .lp-testimonials { grid-template-columns: 1fr; } }
.lp-testi-card {
  background: var(--lp-bg2); border: 1px solid var(--lp-border);
  border-radius: 12px; padding: 22px; transition: all .2s;
}
.lp-testi-card:hover { border-color: var(--lp-border2); transform: translateY(-2px); }
.lp-testi-stars { color: var(--lp-gold); font-size: 13px; letter-spacing: 2px; margin-bottom: 12px; }
.lp-testi-text { font-size: 14px; color: var(--lp-muted); line-height: 1.65; margin-bottom: 18px; font-style: italic; }
.lp-testi-text strong { color: var(--lp-text); font-style: normal; }
.lp-testi-author { display: flex; align-items: center; gap: 10px; }
.lp-testi-av { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; }
.lp-testi-name { font-size: 13px; font-weight: 600; }
.lp-testi-role { font-size: 11px; color: var(--lp-muted); margin-top: 1px; }

/* ── CTA ── */
.lp-cta-section {
  text-align: center; padding: 112px 5vw;
  position: relative; overflow: hidden;
}
.lp-cta-bg {
  position: absolute; inset: 0;
  background: radial-gradient(ellipse 60% 80% at 50% 50%, rgba(245,166,35,0.05) 0%, transparent 70%);
}
.lp-cta-inner { position: relative; z-index: 1; max-width: 680px; margin: 0 auto; }
.lp-cta-title {
  font-weight: 900; font-size: clamp(44px, 7vw, 84px);
  line-height: .9; letter-spacing: -2px;
  margin-bottom: 22px;
}
.lp-cta-title em { font-style: italic; color: var(--lp-accent); }
.lp-cta-sub  { font-size: 17px; color: var(--lp-muted); line-height: 1.6; margin-bottom: 44px; font-weight: 300; }
.lp-cta-actions { display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; }
.lp-cta-fine { font-size: 12px; color: var(--lp-muted2); margin-top: 22px; }

/* ── FOOTER ── */
.lp-footer {
  background: var(--lp-bg2); border-top: 1px solid var(--lp-border);
  padding: 52px 5vw 28px;
}
.lp-footer-inner { max-width: 1200px; margin: 0 auto; }
.lp-footer-top {
  display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 40px;
  padding-bottom: 44px; border-bottom: 1px solid var(--lp-border);
}
@media (max-width: 700px) { .lp-footer-top { grid-template-columns: 1fr; gap: 28px; } }
.lp-footer-logo { margin-bottom: 10px; display: inline-flex; align-items: center; gap: 10px; }
.lp-footer-logo img { height: 44px; width: auto; display: block; }
.lp-footer-desc { font-size: 13px; color: var(--lp-muted); line-height: 1.65; max-width: 280px; }
.lp-footer-col-title { font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--lp-muted); margin-bottom: 14px; }
.lp-footer-link { display: block; font-size: 13px; color: var(--lp-muted); text-decoration: none; margin-bottom: 9px; transition: color .15s; }
.lp-footer-link:hover { color: var(--lp-text); }
.lp-footer-bottom {
  display: flex; align-items: center; justify-content: space-between;
  padding-top: 28px; flex-wrap: wrap; gap: 10px;
  font-size: 12px; color: var(--lp-muted2);
}
.lp-footer-bottom a { color: var(--lp-muted); text-decoration: none; }
.lp-footer-bottom a:hover { color: var(--lp-text); }
.lp-no-bet-badge {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
  color: #f5a623; background: rgba(245,166,35,0.1);
  border: 1px solid rgba(245,166,35,0.2); padding: 4px 12px; border-radius: 99px;
  margin-top: 14px;
}

/* ── SCROLL REVEAL ── */
.lp-reveal { opacity: 0; transform: translateY(24px); transition: opacity .6s ease, transform .6s ease; }
.lp-reveal.visible { opacity: 1; transform: translateY(0); }

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
  .lp-nav .lp-nav-link:not(.lp-nav-cta) { display: none; }
  .lp-hero { padding: 96px 5vw 56px; }
  .lp-section { padding: 64px 5vw; }
  .hero-actions { flex-direction: column; align-items: flex-start; }
}
</style>
</head>

<body class="landing-page">

{{-- NAVBAR --}}
<nav class="lp-nav" id="lp-navbar">
  <a href="{{ url('/') }}" class="lp-logo" aria-label="BolãoFC">
    <img src="{{ asset('img/logo.webp') }}" alt="BolãoFC" class="lp-logo-img" onerror="this.onerror=null;this.src='{{ asset('img/logo.png') }}';">
    <span class="lp-logo-text">Bolão<span>FC</span></span>
  </a>
  <div class="lp-nav-links">
    <a href="#como-funciona" class="lp-nav-link">Como funciona</a>
    <a href="#recursos" class="lp-nav-link">Recursos</a>
    <a href="#pontuacao" class="lp-nav-link">Pontuação</a>
    <a href="{{ route('login') }}" class="lp-nav-link">Entrar</a>
    <a href="{{ route('register') }}" class="lp-nav-cta">
      Criar conta
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </a>
  </div>
</nav>

{{-- HERO --}}
<section class="lp-hero">
  <div class="hero-bg-grid"></div>
  <div class="hero-orb"></div>
  <div class="hero-orb2"></div>

  <div class="hero-inner">
    <div class="hero-eyebrow">
      <div class="eyebrow-dot"></div>
      <span id="live-competition-label"
            data-live-competitions='@json(($liveCompetitions ?? collect())->values())'>
        Copa do Mundo 2026 · Bolão disponível
      </span>
    </div>

    <h1 class="hero-title">
      Dispute<br>com seus<br><em>amigos.</em>
    </h1>

    <p class="hero-sub">
      Palpite nos jogos, acumule pontos e veja quem <strong>realmente entende de futebol</strong>. Plataforma recreativa: sem apostas, sem dinheiro e sem prêmios em dinheiro.
    </p>

    <div class="hero-actions">
      <a href="{{ route('login') }}" class="lp-btn-primary">
        Entrar no bolãoFC
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
      <button class="lp-btn-ghost" onclick="document.querySelector('#como-funciona').scrollIntoView({behavior:'smooth'})">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
        Ver como funciona
      </button>
    </div>
    <p class="lp-section-sub" style="margin-top:10px;max-width:500px;text:italic">* <strong style="color:#fff;">NÃO</strong> fazemos apostas, <strong style="color:#fff;">NÃO</strong> temos vinculos com casa de aposta, <strong style="color:#fff;">NÃO</strong> arrecadamos dinheiro.</p></p>

    @if(($avatarUsers ?? collect())->isNotEmpty())
    <div class="hero-social-proof">
      <div class="sp-avatars">
        @foreach(($avatarUsers ?? collect()) as $av)
          <div class="sp-av" style="background:{{ $av['color'] }}">{{ $av['initials'] }}</div>
        @endforeach
      </div>
      <div class="sp-text">
        @if(($participantCount ?? 0) > 0)
          <strong>{{ ($participantCount ?? 0) >= 1000 ? '+'.number_format(($participantCount ?? 0)/1000, 1, ',', '.').'k' : '+'.($participantCount ?? 0) }} participante{{ ($participantCount ?? 0) !== 1 ? 's' : '' }}</strong> já {{ ($participantCount ?? 0) !== 1 ? 'estão' : 'está' }} no bolão<br>
        @else
          <strong>Seja o primeiro</strong> a entrar no bolão<br>
        @endif
        Copa do Mundo 2026
      </div>
    </div>
    @endif
  </div>

  {{-- Floating Mockup --}}
  <div class="hero-mockup">
    <div class="float-card float-1">
      <div class="fc-label">Seu placar hoje</div>
      <div class="fc-val">+3 pts ⚡</div>
    </div>
    <div class="float-card float-2">
      <div class="fc-label">Sua posição</div>
      <div class="fc-val gold">3° lugar</div>
    </div>

    <div class="mockup-card">
      <div class="mockup-header">
        <span class="mockup-badge">⚽ {{ $heroMatchData['badge'] ?? 'Partida' }}</span>
        <div class="mockup-live">
          @if(($heroMatchData['is_live'] ?? false) === true)
            <div class="live-dot"></div>
          @endif
          {{ $heroMatchData['when'] ?? 'Em breve' }}
        </div>
      </div>
      <div class="mockup-match">
        <div class="mk-team">
          @if(!empty($heroMatchData['home_crest']))
            <img
              src="{{ $heroMatchData['home_crest'] }}"
              alt="Escudo {{ $heroMatchData['home_name'] ?? 'Seleção A' }}"
              class="mk-crest"
              loading="lazy"
              referrerpolicy="no-referrer"
            >
          @else
            <span class="mk-flag">{{ $heroMatchData['home_flag'] ?? '🏳️' }}</span>
          @endif
          <div class="mk-name">{{ $heroMatchData['home_name'] ?? 'Seleção A' }}</div>
        </div>
        <div style="text-align:center">
          <div class="mk-score" style="color:var(--lp-text)">
            {{ isset($heroMatchData['home_score'], $heroMatchData['away_score']) ? $heroMatchData['home_score'].'-'.$heroMatchData['away_score'] : '—' }}
          </div>
          <div class="mk-score-label">vs</div>
        </div>
        <div class="mk-team">
          @if(!empty($heroMatchData['away_crest']))
            <img
              src="{{ $heroMatchData['away_crest'] }}"
              alt="Escudo {{ $heroMatchData['away_name'] ?? 'Seleção B' }}"
              class="mk-crest"
              loading="lazy"
              referrerpolicy="no-referrer"
            >
          @else
            <span class="mk-flag">{{ $heroMatchData['away_flag'] ?? '🏳️' }}</span>
          @endif
          <div class="mk-name">{{ $heroMatchData['away_name'] ?? 'Seleção B' }}</div>
        </div>
      </div>
      <div class="mockup-guess">
        <div class="mk-guess-label"><b>Seu palpite</b>Encerra em 2h 34min</div>
        <div class="mk-inputs">
          <div class="mk-inp">2</div>
          <div class="mk-sep">×</div>
          <div class="mk-inp">1</div>
        </div>
        <button class="mk-submit-btn">✓</button>
      </div>
      <div class="mockup-ranking">
        <div class="mkr-header">Ranking do bolão</div>
        <div class="mkr-row"><div class="mkr-pos" style="color:#fbbf24">1</div><div class="mkr-av" style="background:rgba(251,191,36,.15);color:#fbbf24">JS</div><div class="mkr-name">João Silva</div><div class="mkr-pts" style="color:#fbbf24">108</div></div>
        <div class="mkr-row"><div class="mkr-pos" style="color:#cbd5e1">2</div><div class="mkr-av" style="background:rgba(203,213,225,.1);color:#cbd5e1">MF</div><div class="mkr-name">Marcos F.</div><div class="mkr-pts" style="color:#cbd5e1">92</div></div>
        <div class="mkr-row me"><div class="mkr-pos" style="color:#d97706">3</div><div class="mkr-av" style="background:rgba(245,166,35,.15);color:var(--lp-accent)">RD</div><div class="mkr-name" style="font-weight:600">Você ★</div><div class="mkr-pts">87</div></div>
      </div>
    </div>
  </div>
</section>

{{-- STATS BAR --}}
<div class="lp-stats-bar">
  <div class="lp-stats-inner">
    <div class="lp-stat-item lp-reveal">
      <div class="lp-stat-num">Flexível</div>
      <div class="lp-stat-desc">Pontuação personalizável por bolão</div>
    </div>
    <div class="lp-stat-item lp-reveal">
      <div class="lp-stat-num">{{ number_format((int) ($availablePredictionMatches ?? 0), 0, ',', '.') }}</div>
      <div class="lp-stat-desc">Jogos disponíveis para palpite</div>
    </div>
    <div class="lp-stat-item lp-reveal">
      <div class="lp-stat-num">R$0</div>
      <div class="lp-stat-desc">Sem apostas</div>
    </div>
    <div class="lp-stat-item lp-reveal">
      <div class="lp-stat-num">100%*</div>
      <div class="lp-stat-desc">Gratuito durante o período beta</div>
    </div>
  </div>
</div>

{{-- HOW IT WORKS --}}
<section class="lp-section" id="como-funciona">
  <div class="lp-section-inner">
    <span class="lp-section-label lp-reveal">Como funciona</span>
    <h2 class="lp-section-title lp-reveal">Em três<br><em>passos simples.</em></h2>

    <div class="lp-how-grid lp-reveal">
      <div class="lp-how-card">
        <span class="lp-how-num">01</span>
        <span class="lp-how-icon">🤝</span>
        <div class="lp-how-title">Crie sua conta</div>
        <p class="lp-how-desc">Cadastro gratuito em menos de 30 segundos. Sem cartão de crédito, sem dados financeiros. Só nome e e-mail.</p>
      </div>
      <div class="lp-how-card">
        <span class="lp-how-num">02</span>
        <span class="lp-how-icon">🎯</span>
        <div class="lp-how-title">Dê seus palpites</div>
        <p class="lp-how-desc">Antes de cada jogo começar, registre o placar que você acha que vai acontecer. Cada bolão define a própria janela de palpite, e também pode operar em competição fechada.</p>
      </div>
      <div class="lp-how-card">
        <span class="lp-how-num">03</span>
        <span class="lp-how-icon">🏆</span>
        <div class="lp-how-title">Acumule pontos</div>
        <p class="lp-how-desc">A pontuação do bolão é personalizável. Cada organizador define as regras e o ranking é atualizado em tempo real.</p>
      </div>
    </div>
  </div>
</section>

{{-- FEATURES --}}
<section class="lp-section lp-section-alt" id="recursos">
  <div class="lp-section-inner">
    <div class="lp-features-header">
      <div>
        <span class="lp-section-label lp-reveal">Recursos</span>
        <h2 class="lp-section-title lp-reveal">Tudo que você<br><em>precisa.</em></h2>
      </div>
      <p class="lp-section-sub lp-reveal" style="margin-top:0">Uma plataforma pensada do zero para a melhor experiência de bolão recreativo — no celular ou no computador. Sem apostas financeiras.</p>
    </div>

    <div class="lp-features-grid lp-reveal">
      <div class="lp-feature-card featured">
        <div class="lp-fc-icon green">🎯</div>
        <div class="lp-fc-title">Palpites em tempo real</div>
        <p class="lp-fc-desc">Interface rápida e intuitiva para registrar seus palpites antes de cada partida. Confirmação instantânea com feedback visual claro.</p>
        <span class="lp-fc-badge green">Destaque</span>
      </div>
      <div class="lp-feature-card">
        <div class="lp-fc-icon green">📊</div>
        <div class="lp-fc-title">Ranking ao vivo</div>
        <p class="lp-fc-desc">Acompanhe sua posição e dos participantes em tempo real. Veja tendências de subida e queda a cada jogo encerrado.</p>
      </div>
      <div class="lp-feature-card">
        <div class="lp-fc-icon gold">📈</div>
        <div class="lp-fc-title">Estatísticas detalhadas</div>
        <p class="lp-fc-desc">Seu aproveitamento, total de acertos, placares exatos e histórico completo de palpites — tudo num painel pessoal.</p>
        <span class="lp-fc-badge blue">Novo</span>
      </div>
      <div class="lp-feature-card">
        <div class="lp-fc-icon blue">📱</div>
        <div class="lp-fc-title">Mobile-first</div>
        <p class="lp-fc-desc">Funciona perfeitamente no celular, tablet e computador. Sem instalar nada — acesse direto pelo navegador.</p>
      </div>
    </div>
  </div>
</section>

{{-- SCORING --}}
<section class="lp-section" id="pontuacao">
  <div class="lp-section-inner">
    <div class="lp-scoring-layout">
      <div>
        <span class="lp-section-label lp-reveal">Sistema de pontuação</span>
        <h2 class="lp-section-title lp-reveal">Simples.<br><em>Justo.</em><br>Emocionante.</h2>
        <p class="lp-section-sub lp-reveal">As regras podem ser personalizadas por bolão, mantendo a disputa transparente e divertida para todos.</p>

        <div class="lp-scoring-table lp-reveal">
          <div class="lp-score-row">
            <div class="lp-score-icon">🎯</div>
            <div class="lp-score-info">
              <div class="lp-score-name">Placar exato</div>
              <div class="lp-score-sub">Acertou o resultado cheio — ex: 2×1</div>
            </div>
            <div class="lp-score-pts g">+3</div>
          </div>
          <div class="lp-score-row">
            <div class="lp-score-icon">✔</div>
            <div class="lp-score-info">
              <div class="lp-score-name">Vencedor ou empate certo</div>
              <div class="lp-score-sub">Acertou quem ganhou mas errou o placar</div>
            </div>
            <div class="lp-score-pts b">+1</div>
          </div>
          <div class="lp-score-row">
            <div class="lp-score-icon">✘</div>
            <div class="lp-score-info">
              <div class="lp-score-name">Palpite errado</div>
              <div class="lp-score-sub">Errou o resultado completamente</div>
            </div>
            <div class="lp-score-pts r">0</div>
          </div>
          <div class="lp-score-row">
            <div class="lp-score-icon">⏰</div>
            <div class="lp-score-info">
              <div class="lp-score-name">Prazo dos palpites</div>
              <div class="lp-score-sub">Definido por cada bolão, com opção de competição fechada</div>
            </div>
            <div style="font-size:18px">🔒</div>
          </div>
        </div>
      </div>

      <div class="lp-reveal">
        <div class="lp-ranking-preview">
          <div class="lp-rp-header">
            <div class="lp-rp-title">Ranking · Copa 2026</div>
            <div class="lp-rp-badge">24 participantes</div>
          </div>
          <div class="lp-rp-row">
            <div class="lp-rp-pos" style="color:#fbbf24">1</div>
            <div class="lp-rp-av" style="background:rgba(251,191,36,.15);color:#fbbf24">JS</div>
            <div class="lp-rp-info"><div class="lp-rp-name">João Silva</div><div class="lp-rp-detail">12 exatos · 28 palpites</div></div>
            <div class="lp-rp-pts" style="color:#fbbf24">108</div>
            <div class="trend-up">↑</div>
          </div>
          <div class="lp-rp-row">
            <div class="lp-rp-pos" style="color:#cbd5e1">2</div>
            <div class="lp-rp-av" style="background:rgba(203,213,225,.1);color:#cbd5e1">MF</div>
            <div class="lp-rp-info"><div class="lp-rp-name">Marcos Fonseca</div><div class="lp-rp-detail">9 exatos · 28 palpites</div></div>
            <div class="lp-rp-pts" style="color:#cbd5e1">92</div>
            <div class="trend-eq">→</div>
          </div>
          <div class="lp-rp-row you">
            <div class="lp-rp-pos" style="color:#d97706">3</div>
            <div class="lp-rp-av" style="background:rgba(245,166,35,.15);color:var(--lp-accent)">RD</div>
            <div class="lp-rp-info">
              <div class="lp-rp-name">Rafael Dias <span style="font-size:10px;color:var(--lp-accent)">(você)</span></div>
              <div class="lp-rp-detail">8 exatos · 28 palpites</div>
            </div>
            <div class="lp-rp-pts">87</div>
            <div class="trend-up">↑</div>
          </div>
          <div class="lp-rp-row">
            <div class="lp-rp-pos">4</div>
            <div class="lp-rp-av" style="background:rgba(59,130,246,.12);color:#3b82f6">AL</div>
            <div class="lp-rp-info"><div class="lp-rp-name">Ana Lima</div><div class="lp-rp-detail">7 exatos · 27 palpites</div></div>
            <div class="lp-rp-pts" style="color:var(--lp-text)">82</div>
            <div class="trend-dn">↓</div>
          </div>
          <div class="lp-rp-row">
            <div class="lp-rp-pos">5</div>
            <div class="lp-rp-av" style="background:rgba(34,197,94,.12);color:#22c55e">PC</div>
            <div class="lp-rp-info"><div class="lp-rp-name">Pedro Costa</div><div class="lp-rp-detail">6 exatos · 25 palpites</div></div>
            <div class="lp-rp-pts" style="color:var(--lp-text)">79</div>
            <div class="trend-eq">→</div>
          </div>
          <div style="padding:12px 18px;text-align:center;font-size:12px;color:var(--lp-muted)">
            + 19 participantes no bolão
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- FRIENDS NOTICE --}}
<section class="lp-section lp-section-alt">
  <div class="lp-section-inner">
    <div class="lp-reveal" style="position:relative;text-align:center;padding:8px 0">
      <h2 style="margin:0;color:#e2e8f0;font-size:clamp(44px,7vw,82px);line-height:.9;font-weight:900;letter-spacing:-.03em">
        Um bolão feito de<br><span style="color:#f5a623;font-style:italic">amigos para amigos.</span>
      </h2>
      <p style="margin:14px auto 0 auto;font-size:clamp(11px,1.1vw,14px);line-height:1.5;color:#64748b;max-width:900px">
        * O site não organiza, não participa, não cobra a participação ou se responsabiliza por acordo entre os usuários.
      </p>
    </div>
  </div>
</section>

{{-- CTA --}}
<section class="lp-cta-section">
  <div class="lp-cta-bg"></div>
  <div class="lp-cta-inner lp-reveal">
    <h2 class="lp-cta-title">Pronto para se<br><em>divertir</em>?</h2>
    <p class="lp-cta-sub">Crie sua conta e entre no bolão da Copa do Mundo 2026. Sem apostas, sem dinheiro e sem prêmios financeiros. Não somos casa de apostas.</p>
    <div class="lp-cta-actions">
      <a href="{{ route('login') }}" class="lp-btn-primary" style="font-size:17px;padding:16px 36px">
        Criar conta
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
      <a href="{{ route('login') }}" class="lp-btn-ghost">Já tenho conta</a>
    </div>
    <p class="lp-cta-fine">100% gratuito* · Não somos BET · Sem apostas financeiras<br>*Durante o período beta.</p>
  </div>
</section>

{{-- FOOTER --}}
<footer class="lp-footer">
  <div class="lp-footer-inner">
    <div class="lp-footer-top">
      <div>
        <div class="lp-footer-logo">
          <img src="{{ asset('img/logo.webp') }}" alt="BolãoFC" onerror="this.onerror=null;this.src='{{ asset('img/logo.png') }}';">
          <div class="lp-logo-text">Bolão<span>FC</span></div>
        </div>
        <p class="lp-footer-desc">Plataforma de bolão esportivo recreativo para amigos e grupos. Não realizamos apostas, não somos BET/casa de apostas e não operamos jogos de azar.</p>
        <div class="lp-no-bet-badge">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          100% sem apostas financeiras
        </div>
      </div>
      <div>
        <div class="lp-footer-col-title">Plataforma</div>
        <a href="#como-funciona" class="lp-footer-link">Como funciona</a>
        <a href="#recursos" class="lp-footer-link">Recursos</a>
        <a href="#pontuacao" class="lp-footer-link">Pontuação</a>
        <a href="{{ route('login') }}" class="lp-footer-link">Criar conta</a>
      </div>
      <div>
        <div class="lp-footer-col-title">Suporte</div>
        <a href="{{ route('legal.terms') }}" class="lp-footer-link">Termos de Uso</a>
        <a href="{{ route('legal.privacy-policy') }}" class="lp-footer-link">Privacidade</a>
        <a href="{{ route('about') }}" class="lp-footer-link">Sobre</a>
      </div>
    </div>
    <div class="lp-footer-bottom">
      <div>© {{ date('Y') }} VixForge Sistemas · Feito com ⚽ para fãs de futebol</div>
      <div style="display:flex;gap:18px">
        <a href="{{ route('legal.terms') }}">Termos de uso</a>
        <a href="{{ route('legal.privacy-policy') }}">Política de privacidade</a>
      </div>
    </div>
  </div>
</footer>

<script>
// Navbar scroll
window.addEventListener('scroll', () => {
  document.getElementById('lp-navbar').classList.toggle('scrolled', window.scrollY > 20);
});

// Scroll reveal
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); }
  });
}, { threshold: 0.1 });
document.querySelectorAll('.lp-reveal').forEach(el => observer.observe(el));

// Hero live competition label
(() => {
  const labelEl = document.getElementById('live-competition-label');
  if (!labelEl) return;

  let competitions = [];
  try {
    const parsed = JSON.parse(labelEl.dataset.liveCompetitions || '[]');
    competitions = Array.isArray(parsed)
      ? parsed.map(item => String(item).trim()).filter(Boolean)
      : [];
  } catch (_) {
    competitions = [];
  }

  if (competitions.length === 0) {
    labelEl.textContent = 'Copa do Mundo 2026 · Bolão disponível';
    return;
  }

  const labels = competitions.map(name => `${name} · Ao vivo agora`);
  let index = 0;
  labelEl.textContent = labels[index];

  if (labels.length < 2) return;

  setInterval(() => {
    index = (index + 1) % labels.length;
    labelEl.textContent = labels[index];
  }, 4000);
})();
</script>
@include('layouts.partials.cookie-consent')
</body>
</html>
