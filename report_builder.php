<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
requireLoginPage();
require __DIR__ . '/includes/layout_top.php';
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800&display=swap');
#rbApp{ font-family:'Vazirmatn', Tahoma, sans-serif; }
.rb-toolbar{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:14px; }
.rb-toolbar .rb-title{ flex:1 1 260px; font-weight:800; font-size:1.05rem; }
.rb-status{ font-size:.8rem; color:#8a93a6; min-width:90px; }
.rb-hint{ font-size:.82rem; color:#6b7385; margin-bottom:12px; }

.pg-wrap{ margin-bottom:22px; }
.pg-label{ font-size:.8rem; color:#6b7385; margin-bottom:4px; display:flex; justify-content:space-between; align-items:center; }
.pg-scaler{ position:relative; margin-inline:auto; }
.pg{
  position:absolute; top:0; left:0; width:1122px; height:793px; transform-origin:0 0;
  background:#fff; box-shadow:0 2px 14px rgba(20,35,60,.18); overflow:hidden; direction:ltr;
  background-image:radial-gradient(#e6ebf5 1px, transparent 1px); background-size:24px 24px;
  -webkit-print-color-adjust:exact; print-color-adjust:exact;
}

/* سربرگ: هم‌رنگ هدر سامانه (گرادیانت) */
.pg-hdr{
  position:absolute; top:0; left:0; right:0; height:46px; direction:rtl; display:flex; align-items:center; justify-content:center;
  background:linear-gradient(135deg, #0a1a40 0%, #123a73 55%, #1f5aa8 100%);
}
.pg-hdr-title{ color:#fff; font-weight:800; font-size:18px; outline:none; min-width:200px; text-align:center; padding:2px 12px; border-radius:6px; white-space:pre-wrap; cursor:text; }
.pg-hdr-title:focus{ background:rgba(255,255,255,.12); }
.pg-hdr-title:empty::before{ content:'عنوان گزارش…'; color:rgba(255,255,255,.55); }

/* پاورقی */
.pg-fe{ position:absolute; direction:rtl; }
.pg-fe .fe-x{
  position:absolute; top:-9px; right:-9px; width:16px; height:16px; border:0; border-radius:50%; background:#a33; color:#fff;
  font-size:10px; line-height:16px; padding:0; cursor:pointer; display:none;
}
.pg-fe:hover .fe-x{ display:block; }
.pg-foot-line{ bottom:34px; left:25%; width:50%; height:2px; background:linear-gradient(90deg, rgba(31,90,168,0), #1f5aa8 18%, #123a73 50%, #1f5aa8 82%, rgba(31,90,168,0)); }
.pg-foot-line::before{ content:''; position:absolute; left:0; right:0; top:-8px; bottom:-8px; }
.pg-foot-num{ bottom:8px; left:50%; transform:translateX(-50%); font-size:13px; color:#12314f; font-weight:700; min-width:24px; text-align:center; }
.pg-foot-text{ bottom:8px; left:18px; font-size:12px; color:#33405a; }
.fe-edit{ outline:none; cursor:text; white-space:pre-wrap; }
.fe-edit:empty::before{ content:'…'; color:#b3bbcc; }

/* کارت‌ها */
.ri{
  position:absolute; background:#fff; border:1px solid #d5dcea; border-radius:8px; direction:rtl;
  display:flex; flex-direction:column; overflow:hidden; cursor:move; touch-action:none; user-select:none;
  -webkit-print-color-adjust:exact; print-color-adjust:exact;
}
.ri.sel{ border-color:#1f5aa8; box-shadow:0 0 0 2px rgba(31,90,168,.25); z-index:20; }
.ri.txt{ border-style:dashed; background:transparent; }
.ri-head{ padding:6px 8px 2px; padding-left:92px; }
.ri-title{ font-weight:700; font-size:13px; color:#12314f; outline:none; cursor:text; min-height:1.2em; white-space:pre-wrap; user-select:text; }
.ri-title:empty::before{ content:'عنوان...'; color:#b3bbcc; }
.ri-tools{
  position:absolute; top:3px; left:3px; z-index:4; display:none; gap:2px; align-items:center;
  background:rgba(255,255,255,.96); border:1px solid #d5dcea; border-radius:6px; padding:1px 3px; direction:ltr;
}
.ri:hover .ri-tools, .ri.sel .ri-tools{ display:flex; }
.ri-tools button{ border:0; background:transparent; min-width:20px; height:20px; font-size:11px; font-weight:700; color:#12314f; cursor:pointer; padding:0 2px; border-radius:4px; }
.ri-tools button:hover{ background:#eef2fa; }
.ri-tools button.del{ color:#a33; }
.ri-ctx{ font-size:10px; color:#8a93a6; padding:0 8px; }
.ri-body{ flex:1; min-height:0; padding:4px 8px; container-type:size; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.ri-body img{ width:100%; height:100%; object-fit:contain; pointer-events:none; transform:scale(var(--fs,1)); }
.ri-num{ text-align:center; line-height:1.2; }
.ri-num strong{ display:block; font-size:calc(min(34cqh, 22cqw) * var(--fs,1)); color:#1f5aa8; font-weight:800; }
.ri-tbl{ width:100%; height:100%; overflow:hidden; align-self:flex-start; font-size:calc(11px * var(--fs,1)); }
.ri-tbl table{ width:100%; margin:0; font-size:inherit; }
.ri-tbl th, .ri-tbl td{ padding:2px 5px !important; }
.ri-body.tb{ align-items:flex-start; justify-content:flex-start; padding:8px 10px; }
.ri-text{ width:100%; height:100%; outline:none; white-space:pre-wrap; overflow:hidden; cursor:text; user-select:text; font-size:calc(14px * var(--fs,1)); color:#1d2a44; line-height:1.9; }
.ri-text:empty::before{ content:'متن خود را بنویسید…'; color:#b3bbcc; }
.ri-desc{ font-size:12px; color:#33405a; padding:2px 8px 6px; outline:none; white-space:pre-wrap; cursor:text; min-height:1.2em; user-select:text; }
.ri-desc:empty::before{ content:attr(data-ph); color:#b3bbcc; }
.ri-h{ position:absolute; width:14px; height:14px; z-index:3; }
.ri-h::after{ content:''; position:absolute; width:8px; height:8px; background:#1f5aa8; border-radius:2px; opacity:0; }
.ri:hover .ri-h::after, .ri.sel .ri-h::after{ opacity:.85; }
.ri-h.nw{ top:0; left:0; cursor:nwse-resize; } .ri-h.nw::after{ top:2px; left:2px; }
.ri-h.ne{ top:0; right:0; cursor:nesw-resize; } .ri-h.ne::after{ top:2px; right:2px; }
.ri-h.sw{ bottom:0; left:0; cursor:nesw-resize; } .ri-h.sw::after{ bottom:2px; left:2px; }
.ri-h.se{ bottom:0; right:0; cursor:nwse-resize; } .ri-h.se::after{ bottom:2px; right:2px; }
.rb-empty{ text-align:center; color:#8a93a6; padding:30px 0; }

/* استایل جدول ابر کلمات (کپی از صفحه ارزیابی) */
.wc-word-cell{ display:inline-block; padding:1px 8px; border-radius:6px; }
.wc-count-bar-wrap{ position:relative; background:#eef2fa; border-radius:5px; height:16px; overflow:hidden; }
.wc-count-bar{ position:absolute; inset-inline-start:0; top:0; bottom:0; border-radius:5px; }
.wc-count-bar-text{ position:relative; z-index:1; font-size:10px; font-weight:700; padding-inline-start:8px; line-height:16px; }

@media print{
  @page{ size:A4 landscape; margin:0; }
  body{ background:#fff !important; }
  .app-navbar, .app-footer, .no-print{ display:none !important; }
  .container.app-main{ max-width:none !important; padding:0 !important; margin:0 !important; }
  .pg-wrap{ margin:0; break-after:page; }
  .pg-wrap:last-child{ break-after:auto; }
  .pg-scaler{ width:1122px !important; height:793px !important; margin:0; }
  .pg{ transform:none !important; box-shadow:none; background-image:none; }
  .ri{ cursor:default; }
  .ri.sel{ border-color:#d5dcea; box-shadow:none; }
  .ri.txt{ border-color:transparent; }
  .ri-h, .ri-tools, .fe-x{ display:none !important; }
  .ri-desc:empty{ display:none; }
  .ri-title:empty::before, .ri-desc:empty::before, .ri-text:empty::before, .fe-edit:empty::before, .pg-hdr-title:empty::before{ content:'' !important; }
}
</style>

<div id="rbApp">
  <div class="rb-toolbar no-print">
    <input id="rbTitle" class="form-control rb-title" placeholder="عنوان گزارش" maxlength="200">
    <button class="btn btn-outline-secondary btn-sm" id="btnAddText">＋ باکس متن</button>
    <button class="btn btn-outline-primary btn-sm" id="btnAddPage">＋ صفحه جدید</button>
    <button class="btn btn-success btn-sm" id="btnWord">خروجی Word</button>
    <button class="btn btn-primary btn-sm" id="btnPrint">چاپ / PDF</button>
    <button class="btn btn-outline-secondary btn-sm" id="btnResetChrome" title="بازگرداندن آیتم‌های حذف‌شده‌ی پاورقی و متن‌های پیش‌فرض">بازنشانی سربرگ/پاورقی</button>
    <button class="btn btn-outline-danger btn-sm" id="btnClear">پاک‌کردن همه</button>
    <span class="rb-status" id="rbStatus"></span>
  </div>
  <div class="rb-hint no-print">
    آیتم‌ها را از صفحه‌ی <a href="evaluation.php">ارزیابی خودکار</a> با دکمه‌ی «+» اضافه کنید. کارت را بکشید تا جابه‌جا شود (حتی به صفحه‌ی دیگر)، گوشه‌هایش را بکشید تا اندازه‌اش عوض شود، با A+ / A− محتوا را درشت/ریز کنید و عنوان و توضیح را مستقیم روی آن ویرایش کنید.
    برای چاپ، گزینه‌ی «Background graphics» را فعال کنید.
  </div>
  <div id="pages"></div>
</div>

<script>
(function(){
const PW = 1122, PH = 793, GRID = 8, MIN_W = 110, MIN_H = 70;
const HEADER_H = 46, FOOT_H = 40, TOP = HEADER_H + 10, BOTTOM = PH - FOOT_H, SIDE = 24;
const API = 'report_api.php';
const DEF = { number:[210,100], chart:[520,300], table:[520,300], text:[320,120] };
const FOOT_DEFAULT = { line:true, num:true, showText:true, text:'اداره ارزیابی خبرگزاری دانشجویان ایران (ایسنا)' };
let S = { title:'گزارش ارزیابی', pages:1, items:[], pageTitles:{}, pageNums:{}, footer:{...FOOT_DEFAULT} };
let scale = 1, saveTimer = null;
const $ = id => document.getElementById(id);
const toFa = n => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);

async function api(action, data){
  const opt = data ? { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action, ...data}) } : {};
  const res = await fetch(API + '?action=' + action, opt);
  return res.json();
}
function status(t){ $('rbStatus').textContent = t; }
function snap(v){ return Math.round(v / GRID) * GRID; }
function clamp(v, a, b){ return Math.max(a, Math.min(b, v)); }
function headerTitle(p){ return S.pageTitles[p] !== undefined ? S.pageTitles[p] : S.title; }
function pageNum(p){ return S.pageNums[p] !== undefined ? S.pageNums[p] : toFa(p + 1); }

/* ---------- ذخیره‌ی خودکار ---------- */
async function doSave(){
  const items = S.items.filter(i => i.x !== null).map(i => ({id:i.id, x:i.x, y:i.y, w:i.w, h:i.h, page:i.page, title:i.title, desc:i.desc, fs:i.fs || 1, text:i.text}));
  try {
    const r = await api('layout', {title:S.title, pages:S.pages, items, pageTitles:S.pageTitles, pageNums:S.pageNums, footer:S.footer});
    status(r.ok ? 'ذخیره شد ✓' : 'خطا در ذخیره');
  } catch(e){ status('خطا در ذخیره'); }
}
function scheduleSave(){ status('در حال ذخیره…'); clearTimeout(saveTimer); saveTimer = setTimeout(doSave, 600); }
async function flushSave(){ clearTimeout(saveTimer); await doSave(); }

/* ---------- جای‌گذاری خودکار ---------- */
function overlaps(a, b){ return !(a.x + a.w <= b.x || b.x + b.w <= a.x || a.y + a.h <= b.y || b.y + b.h <= a.y); }
function findFree(page, w, h, ignoreId){
  const others = S.items.filter(i => i.page === page && i.x !== null && i.id !== ignoreId);
  for (let y = TOP; y + h <= BOTTOM; y += GRID * 2)
    for (let x = PW - SIDE - w; x >= SIDE; x -= GRID * 2)   // از راست شروع می‌کند (RTL)
      if (!others.some(o => overlaps({x,y,w,h}, o))) return {x, y};
  return null;
}
function placeItem(it){
  const [dw, dh] = DEF[it.type] || [300, 200];
  it.w = it.w || dw; it.h = it.h || dh; it.fs = it.fs || 1;
  for (let p = it.page || 0; p < 20; p++){
    const pos = findFree(p, it.w, it.h, it.id);
    if (pos){ it.x = pos.x; it.y = pos.y; it.page = p; S.pages = Math.max(S.pages, p + 1); return; }
  }
  it.x = SIDE; it.y = TOP; it.page = 0;
}
function normalize(it){
  it.fs = it.fs || 1;
  if (it.x !== null && it.x !== undefined){ it.y = clamp(it.y, TOP, Math.max(TOP, BOTTOM - it.h)); it.x = clamp(it.x, 0, Math.max(0, PW - it.w)); }
}

/* ---------- پاک‌سازی HTML جدول ---------- */
function safeTable(html){
  const doc = new DOMParser().parseFromString(html, 'text/html');
  doc.querySelectorAll('script,style,iframe,object,embed,link,meta,form').forEach(n => n.remove());
  doc.querySelectorAll('*').forEach(n => {
    [...n.attributes].forEach(a => {
      const nm = a.name.toLowerCase();
      if (nm.startsWith('on') || nm === 'id') n.removeAttribute(a.name);
      if ((nm === 'href' || nm === 'src') && !/^https?:/i.test(a.value)) n.removeAttribute(a.name);
    });
    if (n.tagName === 'A'){ n.setAttribute('target', '_blank'); n.setAttribute('rel', 'noopener'); }
  });
  return doc.body.firstElementChild;
}

/* ---------- ساخت DOM ---------- */
function plainPaste(e){ e.preventDefault(); document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text')); }
function mkBtn(txt, title, cls, fn){ const b = document.createElement('button'); b.type = 'button'; b.textContent = txt; b.title = title; if (cls) b.className = cls; b.addEventListener('click', e => { e.stopPropagation(); fn(); }); return b; }

function bump(it, el, delta){
  it.fs = clamp(Math.round(((it.fs || 1) + delta) * 100) / 100, 0.5, 3);
  el.style.setProperty('--fs', it.fs); scheduleSave();
}

function buildItem(it){
  const isText = it.type === 'text';
  const el = document.createElement('div');
  el.className = 'ri' + (isText ? ' txt' : ''); el.dataset.id = it.id;
  el.style.setProperty('--fs', it.fs || 1);

  const tools = document.createElement('div'); tools.className = 'ri-tools';
  tools.append(
    mkBtn('A−', 'کوچک‌کردن محتوا', '', () => bump(it, el, -0.1)),
    mkBtn('A+', 'بزرگ‌کردن محتوا', '', () => bump(it, el, 0.1)),
    mkBtn('✕', 'حذف از گزارش', 'del', () => removeItem(it))
  );
  el.append(tools);

  if (!isText){
    const head = document.createElement('div'); head.className = 'ri-head';
    const title = document.createElement('div'); title.className = 'ri-title'; title.contentEditable = 'true'; title.textContent = it.title || '';
    title.addEventListener('input', () => { it.title = title.innerText.trim(); scheduleSave(); });
    title.addEventListener('paste', plainPaste);
    head.append(title); el.append(head);
    if (it.ctx && it.type !== 'number'){ const c = document.createElement('div'); c.className = 'ri-ctx'; c.textContent = it.ctx; el.append(c); }
  }

  const body = document.createElement('div'); body.className = 'ri-body' + (isText ? ' tb' : '');
  if (it.type === 'chart'){ const im = document.createElement('img'); im.src = it.img; im.alt = it.title || ''; body.append(im); }
  else if (it.type === 'number'){
    const n = document.createElement('div'); n.className = 'ri-num';
    const val = document.createElement('strong'); val.textContent = it.value || '';
    n.append(val); body.append(n);
  } else if (it.type === 'table'){
    const w = document.createElement('div'); w.className = 'ri-tbl';
    const t = safeTable(it.html); if (t) w.append(t);
    body.append(w);
  } else {
    const tx = document.createElement('div'); tx.className = 'ri-text'; tx.contentEditable = 'true'; tx.textContent = it.text || '';
    tx.addEventListener('input', () => { it.text = tx.innerText; scheduleSave(); });
    tx.addEventListener('paste', plainPaste);
    body.append(tx);
  }
  el.append(body);

  if (!isText){
    const desc = document.createElement('div'); desc.className = 'ri-desc'; desc.contentEditable = 'true'; desc.dataset.ph = 'توضیحات…'; desc.textContent = it.desc || '';
    desc.addEventListener('input', () => { it.desc = desc.innerText.trim(); scheduleSave(); });
    desc.addEventListener('paste', plainPaste);
    el.append(desc);
  }

  ['nw','ne','sw','se'].forEach(d => { const h = document.createElement('div'); h.className = 'ri-h ' + d; h.dataset.dir = d; el.append(h); });
  el.addEventListener('pointerdown', e => startInteract(e, it, el));
  return el;
}

function applyGeom(it, el){ el.style.left = it.x + 'px'; el.style.top = it.y + 'px'; el.style.width = it.w + 'px'; el.style.height = it.h + 'px'; }

// المان قابل ویرایش سربرگ/پاورقی
function editable(cls, text, onInput, deletable){
  const box = document.createElement('div'); box.className = 'pg-fe ' + cls;
  const ed = document.createElement('div'); ed.className = 'fe-edit'; ed.contentEditable = 'true'; ed.textContent = text;
  ed.addEventListener('input', () => onInput(ed.innerText.replace(/\n+$/,''), ed));
  ed.addEventListener('keydown', e => { if (e.key === 'Enter'){ e.preventDefault(); ed.blur(); } });
  ed.addEventListener('paste', plainPaste);
  box.append(ed);
  if (deletable) box.append(mkBtn('✕', 'حذف', 'fe-x', deletable));
  return box;
}

function render(){
  $('rbTitle').value = S.title;
  const host = $('pages'); host.innerHTML = '';
  if (!S.items.length){ host.innerHTML = '<div class="rb-empty no-print">هنوز آیتمی به گزارش اضافه نشده است. از صفحه‌ی ارزیابی خودکار روی «+» کنار نمودارها، جدول‌ها و عددها بزنید، یا «باکس متن» اضافه کنید.</div>'; }
  for (let p = 0; p < S.pages; p++){
    const wrap = document.createElement('div'); wrap.className = 'pg-wrap';
    const lab = document.createElement('div'); lab.className = 'pg-label no-print'; lab.textContent = 'صفحه ' + toFa(p + 1) + ' از ' + toFa(S.pages);
    if (p === S.pages - 1 && S.pages > 1 && !S.items.some(i => i.page === p)){
      const b = document.createElement('button'); b.className = 'btn btn-sm btn-link text-danger p-0'; b.textContent = 'حذف صفحه‌ی خالی';
      b.onclick = () => { S.pages--; render(); scheduleSave(); }; lab.append(b);
    }
    const scaler = document.createElement('div'); scaler.className = 'pg-scaler';
    const pg = document.createElement('div'); pg.className = 'pg'; pg.dataset.page = p;

    // سربرگ (عنوان هر صفحه قابل ویرایش)
    const hdr = document.createElement('div'); hdr.className = 'pg-hdr';
    const ht = document.createElement('div'); ht.className = 'pg-hdr-title'; ht.contentEditable = 'true'; ht.textContent = headerTitle(p);
    ht.addEventListener('input', () => { S.pageTitles[p] = ht.innerText.replace(/\n+$/,''); scheduleSave(); });
    ht.addEventListener('keydown', e => { if (e.key === 'Enter'){ e.preventDefault(); ht.blur(); } });
    ht.addEventListener('paste', plainPaste);
    hdr.append(ht); pg.append(hdr);

    // پاورقی: خط آبی، شماره‌ی صفحه، متن اداره (قابل ویرایش و حذف)
    if (S.footer.line){ const l = document.createElement('div'); l.className = 'pg-fe pg-foot-line'; l.append(mkBtn('✕', 'حذف خط', 'fe-x', () => { S.footer.line = false; render(); scheduleSave(); })); pg.append(l); }
    if (S.footer.num) pg.append(editable('pg-foot-num', pageNum(p), v => { S.pageNums[p] = v; scheduleSave(); }, () => { S.footer.num = false; render(); scheduleSave(); }));
    if (S.footer.showText) pg.append(editable('pg-foot-text', S.footer.text, (v, ed) => {
      S.footer.text = v; scheduleSave();
      document.querySelectorAll('.pg-foot-text .fe-edit').forEach(o => { if (o !== ed) o.textContent = v; });
    }, () => { S.footer.showText = false; render(); scheduleSave(); }));

    S.items.filter(i => i.page === p && i.x !== null).forEach(it => { const el = buildItem(it); applyGeom(it, el); pg.append(el); });
    scaler.append(pg); wrap.append(lab, scaler); host.append(wrap);
  }
  fitScale();
}

function fitScale(){
  const avail = $('rbApp').clientWidth;
  scale = Math.min(1, (avail - 4) / PW);
  document.querySelectorAll('.pg-scaler').forEach(s => { s.style.width = PW * scale + 'px'; s.style.height = PH * scale + 'px'; });
  document.querySelectorAll('.pg').forEach(p => { p.style.transform = 'scale(' + scale + ')'; });
}
window.addEventListener('resize', fitScale);

/* ---------- جابه‌جایی (حتی بین صفحات) و تغییر اندازه ---------- */
function startInteract(e, it, el){
  if (e.button !== 0) return;
  if (e.target.closest('[contenteditable],button,select')) { select(el); return; }
  const dir = e.target.dataset && e.target.dataset.dir ? e.target.dataset.dir : null;
  select(el);
  e.preventDefault();
  try { el.releasePointerCapture(e.pointerId); } catch(_){}
  const sx = e.clientX, sy = e.clientY, o = {x:it.x, y:it.y, w:it.w, h:it.h};
  const r0 = el.parentNode.getBoundingClientRect();
  const grabX = (e.clientX - r0.left) / scale - o.x, grabY = (e.clientY - r0.top) / scale - o.y;
  const move = ev => {
    if (!dir){
      let target = el.parentNode, best = Infinity;
      document.querySelectorAll('.pg').forEach(pg => {
        const r = pg.getBoundingClientRect();
        const d = ev.clientY < r.top ? r.top - ev.clientY : ev.clientY > r.bottom ? ev.clientY - r.bottom : 0;
        if (d < best){ best = d; target = pg; }
      });
      if (target !== el.parentNode){ target.append(el); it.page = +target.dataset.page; }
      const r = target.getBoundingClientRect();
      it.x = clamp(snap((ev.clientX - r.left) / scale - grabX), 0, PW - it.w);
      it.y = clamp(snap((ev.clientY - r.top) / scale - grabY), TOP, Math.max(TOP, BOTTOM - it.h));
    } else {
      const dx = (ev.clientX - sx) / scale, dy = (ev.clientY - sy) / scale;
      let {x, y, w, h} = o;
      if (dir.includes('e')) w = clamp(snap(o.w + dx), MIN_W, PW - o.x);
      if (dir.includes('s')) h = clamp(snap(o.h + dy), MIN_H, BOTTOM - o.y);
      if (dir.includes('w')){ const nx = clamp(snap(o.x + dx), 0, o.x + o.w - MIN_W); w = o.w + (o.x - nx); x = nx; }
      if (dir.includes('n')){ const ny = clamp(snap(o.y + dy), TOP, o.y + o.h - MIN_H); h = o.h + (o.y - ny); y = ny; }
      Object.assign(it, {x, y, w, h});
    }
    applyGeom(it, el);
  };
  const up = () => {
    document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', up); document.removeEventListener('pointercancel', up);
    render(); scheduleSave();   // بازسازی برای به‌روز شدن دکمه‌ی «حذف صفحه‌ی خالی» و ترتیب لایه‌ها
  };
  document.addEventListener('pointermove', move); document.addEventListener('pointerup', up); document.addEventListener('pointercancel', up);
}
function select(el){ document.querySelectorAll('.ri.sel').forEach(x => x.classList.remove('sel')); el.classList.add('sel'); }
document.addEventListener('pointerdown', e => { if (!e.target.closest('.ri')) document.querySelectorAll('.ri.sel').forEach(x => x.classList.remove('sel')); });

/* ---------- عملیات آیتم/صفحه ---------- */
async function removeItem(it){
  if (!confirm('این آیتم از گزارش حذف شود؟')) return;
  S.items = S.items.filter(i => i.id !== it.id); render();
  try { await api('delete', {id: it.id}); status('حذف شد ✓'); } catch(e){ status('خطا در حذف'); }
}
$('btnAddPage').onclick = () => { if (S.pages >= 20) return; S.pages++; render(); scheduleSave(); };
$('btnAddText').onclick = async () => {
  const r = await api('add', {item:{type:'text', title:'', ctx:''}});
  if (!r.ok){ status(r.error || 'خطا'); return; }
  await syncNew();
  const ed = document.querySelector('.ri[data-id="' + r.id + '"] .ri-text');
  if (ed){ ed.closest('.ri').scrollIntoView({block:'center'}); ed.focus(); }
};
$('btnPrint').onclick = () => window.print();
$('btnWord').onclick = async () => { status('در حال ساخت فایل Word…'); await flushSave(); window.location.href = 'report_export_docx.php'; setTimeout(() => status(''), 2500); };
$('btnResetChrome').onclick = () => {
  if (!confirm('سربرگ و پاورقی به حالت پیش‌فرض برگردند؟ (عنوان صفحات و شماره‌های دستی هم پاک می‌شوند)')) return;
  S.footer = {...FOOT_DEFAULT}; S.pageTitles = {}; S.pageNums = {}; render(); scheduleSave();
};
$('btnClear').onclick = async () => {
  if (!S.items.length || !confirm('همه‌ی آیتم‌های گزارش حذف شوند؟')) return;
  await api('clear', {}); S.items = []; S.pages = 1; render(); scheduleSave();
};
$('rbTitle').addEventListener('input', e => {
  S.title = e.target.value.trim() || 'گزارش ارزیابی';
  document.querySelectorAll('.pg').forEach(pg => { const p = +pg.dataset.page; if (S.pageTitles[p] === undefined){ const t = pg.querySelector('.pg-hdr-title'); if (t) t.textContent = S.title; } });
  scheduleSave();
});

/* ---------- بارگذاری و همگام‌سازی ---------- */
function applyReport(rep){
  S.title = rep.title; S.pages = rep.pages || 1;
  S.pageTitles = Object.assign({}, rep.pageTitles || {});   // ممکن است سرور آرایه یا آبجکت بدهد
  S.pageNums = Object.assign({}, rep.pageNums || {});
  S.footer = {...FOOT_DEFAULT, ...(rep.footer || {})};
}
async function load(){
  const r = await api('get');
  if (!r.ok) return;
  applyReport(r.report); S.items = r.report.items;
  S.items.forEach(it => { S.pages = Math.max(S.pages, (it.page || 0) + 1); });
  let dirty = false;
  S.items.forEach(it => { if (it.x === null || it.x === undefined){ placeItem(it); dirty = true; } else normalize(it); });
  render(); if (dirty) scheduleSave();
}
// وقتی کاربر از تب ارزیابی برمی‌گردد، آیتم‌های تازه‌اضافه‌شده را بدون به‌هم‌ریختن چیدمان فعلی بگیر
async function syncNew(){
  const r = await api('get'); if (!r.ok) return;
  const have = new Set(S.items.map(i => i.id));
  const fresh = r.report.items.filter(i => !have.has(i.id));
  if (!fresh.length) return;
  fresh.forEach(it => { S.items.push(it); if (it.x === null || it.x === undefined) placeItem(it); });
  render(); scheduleSave();
}
document.addEventListener('visibilitychange', () => { if (!document.hidden) syncNew(); });
load();
})();
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
