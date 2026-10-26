/* Papikondalu guides: reading progress + small interactive widgets.
   Everything is progressive enhancement; the articles read fine without JS. */
(function () {
  "use strict";
  var WA = "https://wa.me/917207902958?text=";
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function store(k, v) { try { if (v === undefined) return window.localStorage.getItem(k); window.localStorage.setItem(k, v); } catch (e) {} return null; }
  function inr(n) { return "₹" + Number(n).toLocaleString("en-IN"); }

  /* Reading progress bar */
  var bar = $(".pk-progress");
  if (bar) {
    var upd = function () {
      var h = document.documentElement;
      var max = h.scrollHeight - h.clientHeight;
      bar.style.width = (max > 0 ? Math.min(100, (h.scrollTop / max) * 100) : 0) + "%";
    };
    window.addEventListener("scroll", upd, { passive: true }); upd();
  }

  /* Season explorer: reads the month articles that are already in the page as plain HTML */
  $$("[data-pk-season]").forEach(function (root) {
    var src = $(".pk-src", root); if (!src) return;
    var items = $$("article", src);
    var pills = $(".pk-pills", root), img = $(".pk-season-img", root), cap = $(".pk-season-img span", root);
    var title = $(".pk-season-info h4", root), text = $(".pk-season-text", root), verdict = $(".pk-verdict", root), tip = $(".pk-season-tip", root);
    var bars = { mist: $("[data-m=mist]", root), cool: $("[data-m=cool]", root), crowd: $("[data-m=crowd]", root), green: $("[data-m=green]", root) };
    items.forEach(function (it, i) {
      var b = document.createElement("button");
      b.type = "button"; b.className = "pk-pill"; b.textContent = it.getAttribute("data-month"); b.setAttribute("aria-pressed", "false");
      b.addEventListener("click", function () { show(i); });
      pills.appendChild(b);
    });
    root.classList.add("pk-js");
    function show(i) {
      var it = items[i];
      $$(".pk-pill", pills).forEach(function (p, k) { p.setAttribute("aria-pressed", k === i ? "true" : "false"); });
      img.style.backgroundImage = "url('" + it.getAttribute("data-img") + "')";
      img.setAttribute("aria-label", it.getAttribute("data-alt")); cap.textContent = it.getAttribute("data-cap");
      title.textContent = $("h4", it).textContent; text.textContent = $("p", it).textContent; tip.textContent = $(".tip", it).textContent;
      verdict.textContent = it.getAttribute("data-verdict");
      Object.keys(bars).forEach(function (k) { bars[k].style.width = it.getAttribute("data-" + k) + "%"; });
    }
    var short = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"][new Date().getMonth()];
    var start = 0; items.forEach(function (it, i) { if (it.getAttribute("data-month") === short) start = i; });
    if (start === 0 && short !== items[0].getAttribute("data-month")) start = Math.min(2, items.length - 1);
    show(start);
  });

  /* Packing checklist with remembered ticks */
  $$("[data-pk-pack]").forEach(function (root) {
    var boxes = $$("input[type=checkbox]", root), fill = $(".pk-packbar i", root), txt = $(".pk-packtxt", root);
    var key = "pk-pack-" + (root.getAttribute("data-pk-pack") || "x");
    var saved = []; try { saved = JSON.parse(store(key) || "[]"); } catch (e) {}
    boxes.forEach(function (b, i) { if (saved.indexOf(i) > -1) b.checked = true; b.addEventListener("change", sync); });
    function sync() {
      var done = boxes.filter(function (b) { return b.checked; }).length;
      fill.style.width = (done / boxes.length * 100) + "%";
      txt.textContent = done === boxes.length ? "All packed. Have a wonderful trip!" : done + " of " + boxes.length + " packed";
      store(key, JSON.stringify(boxes.map(function (b, i) { return b.checked ? i : -1; }).filter(function (x) { return x > -1; })));
    }
    sync();
  });

  /* Hour-by-hour timeline: reads the stops that are already in the page as an ordered list */
  $$("[data-pk-timeline]").forEach(function (root) {
    var src = $(".pk-src", root); if (!src) return;
    var items = $$("li", src), card = $(".pk-tl-card", root), rail = $(".pk-tl-rail", root), cur = 0, stops = [];
    var im = $(".pk-tl-img", card), tm = $(".pk-tl-time", card), h = $("h3", card), p1 = $(".pk-tl-p1", card), p2 = $(".pk-tl-p2", card);
    items.forEach(function (it, k) {
      var b = document.createElement("button"); b.type = "button"; b.className = "pk-tl-stop"; b.setAttribute("role", "tab"); b.setAttribute("aria-selected", "false");
      b.innerHTML = "<b></b><small></small>"; $("b", b).textContent = it.getAttribute("data-time"); $("small", b).textContent = it.getAttribute("data-label");
      b.addEventListener("click", function () { go(k); }); rail.appendChild(b); stops.push(b);
    });
    root.classList.add("pk-js");
    function go(i) {
      cur = Math.max(0, Math.min(items.length - 1, i)); var it = items[cur], ps = $$("p", it);
      stops.forEach(function (s, k) { s.setAttribute("aria-selected", k === cur ? "true" : "false"); });
      im.style.backgroundImage = "url('" + it.getAttribute("data-img") + "')"; im.setAttribute("aria-label", it.getAttribute("data-alt"));
      tm.textContent = it.getAttribute("data-time"); h.textContent = $("h3", it).textContent.replace(/^[^:]*:\s*/, ""); p1.textContent = ps[0] ? ps[0].textContent : ""; p2.textContent = ps[1] ? ps[1].textContent : "";
      var sel = stops[cur]; rail.scrollLeft = sel.offsetLeft - rail.clientWidth / 2 + sel.clientWidth / 2;
    }
    var prev = $("[data-tl-prev]", root), next = $("[data-tl-next]", root);
    if (prev) prev.addEventListener("click", function () { go(cur - 1); });
    if (next) next.addEventListener("click", function () { go(cur + 1); });
    go(0);
  });

  /* Cost planner: prices are the published package prices on the site */
  $$("[data-pk-planner]").forEach(function (root) {
    var PKG = {
      day: { name: "Papikondalu 1 Day Tour", adult: 1300, child: 1100, min: 1, note: "Includes breakfast and vegetarian lunch on the boat." },
      night: { name: "Papikondalu Night Stay (2 days / 1 night)", adult: 5500, child: 5000, min: 4, note: "Minimum 4 members. Stay at Papikondalu Resorts, Koruturu, with dinner and campfire." },
      bhad1: { name: "Bhadrachalam 1 Day Package", adult: 2200, child: 2000, min: 1, note: "Papikondalu boat ride plus Bhadrachalam temple." },
      bhad2: { name: "Bhadrachalam River cum Road (2 days / 1 night)", adult: 5500, child: 4800, min: 8, note: "A/C price shown. Non-A/C is ₹5,100 per adult. Minimum 8 members." }
    };
    var f = { pkg: $("[name=pkg]", root), a: $("[name=adults]", root), c: $("[name=kids]", root), d: $("[name=date]", root) };
    var total = $(".pk-total", root), line = $(".pk-line", root), warn = $(".pk-warnline", root), wa = $(".pk-wa", root);
    function calc() {
      var p = PKG[f.pkg.value], a = Math.max(0, parseInt(f.a.value || 0, 10)), c = Math.max(0, parseInt(f.c.value || 0, 10));
      var sum = a * p.adult + c * p.child, n = a + c;
      total.textContent = inr(sum);
      line.textContent = a + " adult" + (a === 1 ? "" : "s") + " × " + inr(p.adult) + (c ? " + " + c + " child" + (c === 1 ? "" : "ren") + " × " + inr(p.child) : "") + ". " + p.note;
      warn.textContent = n < p.min ? "Heads up: this package needs a minimum of " + p.min + " members. Please call us to check what is possible for a smaller group." : "";
      var msg = "Hi, I'd like to book the " + p.name + " for " + a + " adult(s)" + (c ? " and " + c + " child(ren)" : "") + (f.d.value ? " on " + f.d.value : "") + ". Estimated " + inr(sum) + ". Please confirm availability.";
      wa.href = WA + encodeURIComponent(msg);
    }
    ["change", "input"].forEach(function (ev) { root.addEventListener(ev, calc); });
    var today = new Date(); today.setDate(today.getDate() + 1);
    if (f.d) f.d.min = today.toISOString().slice(0, 10);
    calc();
  });

  /* "Which trip suits me?" quiz */
  $$("[data-pk-quiz]").forEach(function (root) {
    var out = $(".pk-quiz-out", root), btn = $("[data-quiz-go]", root);
    var R = {
      day: { t: "The 1-Day Papikondalu Tour", d: "You want the full river-and-hills experience and a bed at home by evening. The day tour covers Gandipochamma Temple, the Papikondalu hills, Perantapalli and meals on the boat.", p: "Papikondalu 1 Day Tour", price: "₹1,300 per adult, ₹1,100 per child" },
      night: { t: "The Night Stay at Koruturu", d: "You want to slow down: a bamboo-cottage evening, campfire, an early-morning forest trek and the hills at both ends of the day.", p: "Papikondalu Night Stay", price: "₹5,500 per adult, ₹5,000 per child (min. 4 members)" },
      bhad: { t: "The Bhadrachalam Combination", d: "You want the river plus a pilgrimage. Boat through Papikondalu, then on to Bhadrachalam temple. Choose the 1-day or the relaxed 2-day river-cum-road plan.", p: "Bhadrachalam package", price: "from ₹2,200 per adult (1 day)" }
    };
    btn.addEventListener("click", function () {
      var s = { day: 0, night: 0, bhad: 0 }, got = 0;
      $$(".pk-q", root).forEach(function (q) {
        var c = $("input:checked", q); if (!c) return; got++;
        c.value.split(",").forEach(function (k) { if (k) s[k]++; });
      });
      if (got < $$(".pk-q", root).length) { out.className = "pk-quiz-out show"; out.innerHTML = "<p>Please answer all the questions first.</p>"; return; }
      var best = Object.keys(s).sort(function (a, b) { return s[b] - s[a]; })[0], r = R[best];
      out.className = "pk-quiz-out show";
      out.innerHTML = "<h4></h4><p class='pk-qd'></p><p><strong class='pk-qp'></strong></p><a class='btn btn-gold' target='_blank' rel='noopener'><i class='bi bi-whatsapp'></i> Ask about this trip</a>";
      $("h4", out).textContent = r.t; $(".pk-qd", out).textContent = r.d; $(".pk-qp", out).textContent = r.price;
      $("a", out).href = WA + encodeURIComponent("Hi, I'd like to know more about the " + r.p + ".");
      out.scrollIntoView({ behavior: "smooth", block: "nearest" });
    });
  });
})();
