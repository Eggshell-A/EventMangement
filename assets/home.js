(function () {
  document.documentElement.classList.add('js');
  document.addEventListener('click', function (event) {
    var control = event.target.closest('[data-confirm]');
    if (!control) return;
    if (!window.confirm(control.getAttribute('data-confirm'))) {
      event.preventDefault();
    }
  });
})();
