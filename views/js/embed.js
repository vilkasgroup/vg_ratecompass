(function () {
  const rcScript = document.createElement('script');
  rcScript.type = 'text/javascript';
  rcScript.async = true;
  rcScript.src = VG_RATECOMPASS_EMBED_URL;
  (
    document.getElementsByTagName('head')[0] ||
    document.getElementsByTagName('body')[0]
  ).appendChild(rcScript);
})();
