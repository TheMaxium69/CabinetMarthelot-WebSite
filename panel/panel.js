/* Panel : confirmation avant suppression (pas de JS inline à cause de la CSP) */
document.querySelectorAll("form[data-confirm]").forEach(function (form) {
  form.addEventListener("submit", function (e) {
    if (!confirm(form.getAttribute("data-confirm"))) e.preventDefault();
  });
});
