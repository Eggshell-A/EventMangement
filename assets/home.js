(function () {
  var root = document.querySelector("[data-banner]");
  if (!root) return;
  var slides = Array.prototype.slice.call(root.querySelectorAll(".banner-slide"));
  var dots = Array.prototype.slice.call(root.querySelectorAll("[data-banner-dot]"));
  if (slides.length < 2) return;
  var index = 0;
  var timer = 0;
  function show(next) {
    index = (next + slides.length) % slides.length;
    slides.forEach(function (slide, i) {
      slide.classList.toggle("is-on", i === index);
    });
    dots.forEach(function (dot, i) {
      dot.classList.toggle("is-on", i === index);
    });
  }
  function restart() {
    window.clearInterval(timer);
    timer = window.setInterval(function () { show(index + 1); }, 5000);
  }
  var prev = root.querySelector("[data-banner-prev]");
  var next = root.querySelector("[data-banner-next]");
  if (prev) prev.addEventListener("click", function () { show(index - 1); restart(); });
  if (next) next.addEventListener("click", function () { show(index + 1); restart(); });
  dots.forEach(function (dot) {
    dot.addEventListener("click", function () {
      show(Number(dot.getAttribute("data-banner-dot")));
      restart();
    });
  });
  restart();
})();
