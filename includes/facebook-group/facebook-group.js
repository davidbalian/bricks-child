(function () {
  "use strict";

  function trackClick(element, action) {
    var placement = element.getAttribute("data-autoagora-facebook-placement") || "unknown";
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: "autoagora_facebook_group_click",
      facebook_group_placement: placement,
      facebook_group_action: action || element.getAttribute("data-autoagora-facebook-action") || "join",
    });
  }

  function fallbackCopy(text) {
    return new Promise(function (resolve, reject) {
      var textArea = document.createElement("textarea");
      textArea.value = text;
      textArea.setAttribute("readonly", "");
      textArea.style.position = "fixed";
      textArea.style.opacity = "0";
      document.body.appendChild(textArea);
      textArea.select();

      try {
        if (document.execCommand("copy")) {
          resolve();
        } else {
          reject(new Error("copy_failed"));
        }
      } catch (error) {
        reject(error);
      } finally {
        textArea.remove();
      }
    });
  }

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return fallbackCopy(text);
  }

  function showShareFeedback(button, succeeded) {
    var control = button.closest(".autoagora-facebook-share-control");
    var feedback = control ? control.querySelector(".autoagora-facebook-share-feedback") : null;
    var settings = window.autoagoraFacebookGroup || {};
    if (feedback) {
      feedback.textContent = succeeded
        ? settings.copiedMessage || "Copied. Paste the details into your Facebook post."
        : settings.copyFailedMessage || "The group is open. Copy your listing link into a new Facebook post.";
    }
    if (succeeded) {
      button.classList.add("is-copied");
    }
  }

  function handleShareButton(button) {
    var groupUrl = button.getAttribute("data-group-url");
    var shareCopy = button.getAttribute("data-share-copy") || window.location.href;
    var groupWindow = groupUrl ? window.open(groupUrl, "_blank") : null;
    if (groupWindow) {
      groupWindow.opener = null;
    }

    trackClick(button, "share_listing");
    copyText(shareCopy)
      .then(function () {
        showShareFeedback(button, true);
      })
      .catch(function () {
        showShareFeedback(button, false);
      });
  }

  function relocateSingleListingCard() {
    var placement = document.querySelector("[data-autoagora-facebook-single-placement]");
    var settings = window.autoagoraFacebookGroup || {};
    var relatedCarsLabel = String(settings.relatedCarsLabel || "Related Cars").trim().toLowerCase();
    if (!placement) {
      return;
    }

    var relatedSection = document.querySelector("#brxe-pwdgqz");
    if (!relatedSection) {
      Array.prototype.some.call(document.querySelectorAll("main section"), function (section) {
        var heading = section.querySelector("h2, h3, h4");
        if (heading && heading.textContent.trim().toLowerCase() === relatedCarsLabel) {
          relatedSection = section;
          return true;
        }
        return false;
      });
    }

    if (relatedSection && relatedSection.parentNode) {
      placement.classList.add("autoagora-facebook-single-wrapper");
      placement.hidden = false;
      relatedSection.parentNode.insertBefore(placement, relatedSection);
    }
  }

  function relocateFooterLink() {
    var groupLink = document.querySelector("[data-autoagora-facebook-footer-link]");
    if (!groupLink) {
      return;
    }

    var footer = document.querySelector("footer, #brx-footer, [role='contentinfo']");
    if (!footer) {
      return;
    }

    var pageLink = footer.querySelector("a[href*='facebook.com']");
    if (!pageLink || !pageLink.parentNode) {
      return;
    }

    groupLink.className = pageLink.className;
    groupLink.hidden = false;
    pageLink.parentNode.insertBefore(groupLink, pageLink.nextSibling);
  }

  function initialize() {
    relocateSingleListingCard();
    relocateFooterLink();

    document.addEventListener("click", function (event) {
      var shareButton = event.target.closest("[data-autoagora-facebook-share]");
      if (shareButton) {
        event.preventDefault();
        handleShareButton(shareButton);
        return;
      }

      var trackedLink = event.target.closest("[data-autoagora-facebook-placement]");
      if (trackedLink) {
        trackClick(trackedLink);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initialize);
  } else {
    initialize();
  }
})();
