/* ==========================================================
   MADHU'S TOURS & TRAVELS — site.js
   Mobile nav, sticky header, scroll-reveal, counters,
   FAQ accordion, back-to-top, WhatsApp-based form submit.
========================================================== */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", init);

  function init() {
    stickyHeader();
    mobileNav();
    dropdownTouchToggle();
    revealOnScroll();
    counters();
    faqAccordion();
    backToTop();
    yearStamp();
    contactForm();
    activeNavHighlight();
  }

  /* Sticky header shadow on scroll */
  function stickyHeader() {
    const header = document.getElementById("site-header");
    if (!header) return;
    const toggle = () => header.classList.toggle("is-scrolled", window.scrollY > 12);
    document.addEventListener("scroll", toggle, { passive: true });
    toggle();
  }

  /* Mobile slide-in nav */
  function mobileNav() {
    const btn = document.querySelector(".nav-toggle");
    const backdrop = document.querySelector(".nav-backdrop");
    const close = () => {
      document.body.classList.remove("nav-open");
      if (btn) {
        btn.setAttribute("aria-expanded", "false");
        btn.setAttribute("aria-label", "Open menu");
      }
    };
    if (btn) btn.addEventListener("click", () => {
      const isOpen = document.body.classList.toggle("nav-open");
      btn.setAttribute("aria-expanded", String(isOpen));
      btn.setAttribute("aria-label", isOpen ? "Close menu" : "Open menu");
    });
    if (backdrop) backdrop.addEventListener("click", close);
    document.querySelectorAll(".main-nav a:not(.has-dropdown > a)").forEach((a) => {
      a.addEventListener("click", close);
    });
  }

  /* On mobile, tapping a dropdown parent opens the submenu instead of navigating */
  function dropdownTouchToggle() {
    if (window.matchMedia("(min-width:1141px)").matches) return;
    document.querySelectorAll(".has-dropdown > a").forEach((link) => {
      link.addEventListener("click", function (e) {
        const parent = this.parentElement;
        if (!parent.classList.contains("open")) {
          e.preventDefault();
          document.querySelectorAll(".has-dropdown.open").forEach((el) => { if (el !== parent) el.classList.remove("open"); });
          parent.classList.add("open");
        }
      });
    });
  }

  /* Highlight current page in nav based on filename */
  function activeNavHighlight() {
    const currentFile = location.pathname.split("/").pop() || "index.html";
    document.querySelectorAll(".main-nav a[href]").forEach((a) => {
      const href = a.getAttribute("href");
      if (!href || href.startsWith("#") || href.startsWith("http") || href.includes("#")) return;
      const hrefFile = href.split("/").pop().split("?")[0].split("#")[0];
      if (hrefFile === currentFile || (currentFile === "" && hrefFile === "index.html")) {
        a.closest("li").classList.add("active");
      }
    });
  }

  /* Simple fade/slide reveal using IntersectionObserver */
  function revealOnScroll() {
    const els = document.querySelectorAll("[data-reveal]");
    if (!els.length || !("IntersectionObserver" in window)) {
      els.forEach((el) => el.classList.add("in"));
      return;
    }
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("in");
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15 }
    );
    els.forEach((el) => io.observe(el));
  }

  /* Animated number counters e.g. <span data-counter="150">0</span> */
  function counters() {
    const els = document.querySelectorAll("[data-counter]");
    if (!els.length) return;
    const animate = (el) => {
      const target = parseFloat(el.getAttribute("data-counter"));
      const suffix = el.getAttribute("data-suffix") || "";
      const dur = 1400;
      const start = performance.now();
      function tick(now) {
        const p = Math.min(1, (now - start) / dur);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * eased) + suffix;
        if (p < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
    };
    if (!("IntersectionObserver" in window)) { els.forEach(animate); return; }
    const io = new IntersectionObserver((entries) => {
      entries.forEach((e) => { if (e.isIntersecting) { animate(e.target); io.unobserve(e.target); } });
    }, { threshold: 0.5 });
    els.forEach((el) => io.observe(el));
  }

  /* FAQ accordion */
  function faqAccordion() {
    document.querySelectorAll(".faq-q").forEach((btn) => {
      btn.addEventListener("click", () => {
        const item = btn.closest(".faq-item");
        const wasOpen = item.classList.contains("open");
        item.parentElement.querySelectorAll(".faq-item.open").forEach((el) => el.classList.remove("open"));
        if (!wasOpen) item.classList.add("open");
      });
    });
  }

  /* Back-to-top FAB */
  function backToTop() {
    const btn = document.querySelector(".fab-top");
    if (!btn) return;
    document.addEventListener("scroll", () => {
      btn.classList.toggle("show", window.scrollY > 500);
    }, { passive: true });
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  function yearStamp() {
    document.querySelectorAll("[data-year]").forEach((el) => (el.textContent = new Date().getFullYear()));
  }

  /* Contact / enquiry forms: validate, then hand off to WhatsApp (no backend available) */
  function contactForm() {
    document.querySelectorAll("form[data-whatsapp-form]").forEach((form) => {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        const status = form.querySelector(".form-status");
        const required = form.querySelectorAll("[required]");
        let valid = true;
        required.forEach((field) => {
          if (!field.value.trim()) { valid = false; field.style.borderColor = "#B23A2C"; }
          else { field.style.borderColor = ""; }
        });
        if (!valid) {
          if (status) { status.textContent = "Please fill in all required fields."; status.className = "form-status show err"; }
          return;
        }
        const number = form.getAttribute("data-whatsapp-form");
        const data = new FormData(form);
        let msg = "Hi Madhu's Tours & Travels, I'd like to enquire:%0A";
        data.forEach((value, key) => {
          if (value) msg += `%0A*${key}:* ${encodeURIComponent(value)}`;
        });
        if (status) { status.textContent = "Opening WhatsApp with your enquiry\u2026"; status.className = "form-status show ok"; }
        window.open(`https://wa.me/${number}?text=${msg}`, "_blank");
        form.reset();
      });
    });
  }
})();

  // Two-level Blog menu on mobile: tap a service to reveal its articles.
  document.querySelectorAll('.blog-service > a').forEach((link) => {
    link.addEventListener('click', (e) => {
      if (window.matchMedia('(max-width: 1140px)').matches) {
        const parent = link.parentElement;
        const submenu = parent.querySelector('.blog-subpanel');
        if (submenu) {
          e.preventDefault();
          parent.classList.toggle('open');
        }
      }
    });
  });
