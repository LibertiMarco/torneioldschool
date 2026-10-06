// Let the containing page scroll, including long lists of generated graphics.
(() => {
  if (window.parent === window) return;
  const main = document.querySelector('main');
  if (!main) return;
  let queued = false, lastHeight = 0;
  function reportHeight() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => {
      queued = false;
      const rect = main.getBoundingClientRect();
      if (!rect.width) return;
      const style = getComputedStyle(main);
      const height = Math.ceil(rect.height + (parseFloat(style.marginTop) || 0) + (parseFloat(style.marginBottom) || 0));
      if (Math.abs(height - lastHeight) > 1) {
        lastHeight = height;
        window.parent.postMessage({type:'graphics-frame-height', height}, window.location.origin);
      }
    });
  }
  if (typeof ResizeObserver !== 'undefined') new ResizeObserver(reportHeight).observe(main);
  window.addEventListener('load', reportHeight);
  window.addEventListener('resize', reportHeight);
  if (document.fonts) document.fonts.ready.then(reportHeight);
  reportHeight();
})();
