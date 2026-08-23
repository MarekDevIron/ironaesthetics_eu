<?php return <<<'CSS'
@font-face { font-family: 'Lato'; font-weight: 400; font-style: normal; font-display: swap;
    src: url('/fonts/LatoLatin-Regular.woff2') format('woff2'); }
@font-face { font-family: 'Lato'; font-weight: 600; font-style: normal; font-display: swap;
    src: url('/fonts/LatoLatin-Semibold.woff2') format('woff2'); }
@font-face { font-family: 'Lato'; font-weight: 900; font-style: normal; font-display: swap;
    src: url('/fonts/LatoLatin-Black.woff2') format('woff2'); }

* { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; }
body {
    min-height: 100%;
    font-family: 'Lato', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background: #0a0a0b;
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.wrap {
    width: 100%;
    max-width: 880px;
    padding: 48px 24px 40px;
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    justify-content: center;
}
.logo { width: 190px; max-width: 60vw; height: auto; margin-bottom: 44px; }

.kicker {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .28em;
    text-transform: uppercase;
    color: #9d3552;
    margin-bottom: 14px;
}
h1.title {
    font-size: clamp(22px, 4vw, 34px);
    font-weight: 900;
    letter-spacing: .02em;
    text-transform: uppercase;
    line-height: 1.25;
    text-align: center;
    margin-bottom: 40px;
    word-break: break-word;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    width: 100%;
}
.btn {
    position: relative;
    display: flex;
    align-items: center;
    gap: 16px;
    background: #131315;
    border: 1px solid #26262b;
    border-radius: 12px;
    color: #fff;
    text-decoration: none;
    padding: 18px 22px;
    transition: border-color .15s ease, transform .15s ease, background .15s ease;
}
.btn:hover {
    border-color: #9d3552;
    background: #18161a;
    transform: translateY(-2px);
}
.btn img.flag { width: 32px; height: auto; border-radius: 3px; flex: none; }
.btn .txt { line-height: 1.2; min-width: 0; }
.btn .country { display: block; font-size: 17px; font-weight: 900; letter-spacing: .06em; text-transform: uppercase; }
.btn .domain  { display: block; font-size: 13px; font-weight: 400; color: #8e8e94; margin-top: 3px; }
.btn .arrow   { margin-left: auto; color: #9d3552; font-size: 20px; flex: none; transition: transform .15s ease; }
.btn:hover .arrow { transform: translateX(3px); }

/* odporúčaný obchod podľa geolokácie — odznak na hornej hrane, neprekrýva text */
.btn.rec { border-color: #9d3552; background: #18161a; }
.btn.rec .arrow { display: none; }
.btn .chip {
    position: absolute;
    top: -11px;
    right: 14px;
    background: #9d3552;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    border-radius: 999px;
    padding: 4px 11px;
    white-space: nowrap;
}

.muted { color: #77777d; font-size: 14px; }
.muted code { background: #17171a; border: 1px solid #26262b; border-radius: 6px; padding: 2px 9px; font-size: 0.95em; color: #c9c9cf; }
.center { text-align: center; }
.center p { margin-top: 6px; }

.big-code {
    font-size: clamp(52px, 10vw, 84px);
    font-weight: 900;
    color: #9d3552;
    letter-spacing: .04em;
    line-height: 1;
    margin-bottom: 10px;
}

footer.credit {
    padding: 18px 0 26px;
    font-size: 11px;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: #55555b;
}

@media (max-width: 560px) {
    .grid { grid-template-columns: 1fr; gap: 12px; }
    .logo { margin-bottom: 32px; }
    h1.title { margin-bottom: 28px; }
}
CSS;
