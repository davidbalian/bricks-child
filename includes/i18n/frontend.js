(function (window) {
  "use strict";

  const data = window.autoagoraI18nData || {};
  const strings = data.strings || {};

  /**
   * Translate a code-owned frontend string and optionally replace printf-style
   * placeholders. The English source remains the fallback and stable key.
   */
  window.autoagoraTranslate = function (source, ...values) {
    let translated = Object.prototype.hasOwnProperty.call(strings, source)
      ? strings[source]
      : source;

    if (!values.length) {
      return translated;
    }

    let nextValue = 0;
    return translated.replace(/%(?:(\d+)\$)?[sd]/g, function (match, position) {
      const index = position ? Number(position) - 1 : nextValue++;
      return typeof values[index] === "undefined" ? match : String(values[index]);
    });
  };
})(window);
