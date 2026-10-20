/* Cabinet Marthelot — scripts du site (conception Tyrolium) */
(function () {
  "use strict";

  document.documentElement.classList.add("js");

  // Menu mobile
  var toggle = document.querySelector(".nav-toggle");
  if (toggle) {
    toggle.addEventListener("click", function () {
      var open = document.body.classList.toggle("nav-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    document.querySelectorAll(".main-nav a").forEach(function (link) {
      link.addEventListener("click", function () {
        document.body.classList.remove("nav-open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  // Ombre de l'en-tête au défilement
  var header = document.querySelector(".site-header");
  function onScroll() {
    if (header) header.classList.toggle("is-scrolled", window.scrollY > 8);
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  // Apparition progressive des blocs
  var reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
    reveals.forEach(function (el) { observer.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add("is-visible"); });
  }

  // Année courante
  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = new Date().getFullYear();
  });

  // Formulaire de contact : validation puis ouverture de la messagerie
  var form = document.getElementById("contact-form");
  if (!form) return;

  // Pré-sélection du sujet via ?sujet=...
  var params = new URLSearchParams(window.location.search);
  var subjectParam = params.get("sujet");
  if (subjectParam && form.sujet) {
    Array.prototype.forEach.call(form.sujet.options, function (opt) {
      if (opt.value === subjectParam) form.sujet.value = subjectParam;
    });
  }

  function setInvalid(el, invalid) {
    var wrap = el.closest(".field") || el.closest(".consent");
    if (wrap) wrap.classList.toggle("invalid", invalid);
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var valid = true;
    var first = null;

    form.querySelectorAll("[required]").forEach(function (el) {
      var ok = el.type === "checkbox" ? el.checked : el.value.trim() !== "";
      if (ok && el.type === "email") ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value.trim());
      setInvalid(el, !ok);
      if (!ok) {
        valid = false;
        if (!first) first = el;
      }
    });

    if (!valid) {
      first.focus();
      return;
    }

    var d = form.elements;
    var body = [
      "Nom : " + d.nom.value.trim(),
      "Société : " + (d.societe.value.trim() || "—"),
      "Structure : " + (d.structure.value || "—"),
      "E-mail : " + d.email.value.trim(),
      "Téléphone : " + (d.telephone.value.trim() || "—"),
      "",
      d.message.value.trim()
    ].join("\n");

    var subject = "[Site] " + d.sujet.value + " — " + d.nom.value.trim();
    var to = form.getAttribute("data-to");
    window.location.href = "mailto:" + to +
      "?subject=" + encodeURIComponent(subject) +
      "&body=" + encodeURIComponent(body);

    var status = form.querySelector(".form-status");
    if (status) status.classList.add("show");
  });

  form.querySelectorAll("input, select, textarea").forEach(function (el) {
    el.addEventListener("input", function () { setInvalid(el, false); });
    el.addEventListener("change", function () { setInvalid(el, false); });
  });
})();
