(function () {
  "use strict";

  var bannerDismissalKey = "autoagora_fb_group_banner_dismissed_until";
  var bannerDismissalDays = 14;

  function getTrackingContext(element) {
    var banner = element ? element.closest("[data-autoagora-facebook-campaign-banner]") : null;
    var settings = window.autoagoraFacebookGroup || {};

    return {
      pageType: banner ? banner.getAttribute("data-autoagora-facebook-page-type") || "unknown" : "other",
      campaign: banner ? banner.getAttribute("data-autoagora-facebook-campaign") || "unknown" : "evergreen",
      language: settings.language || document.documentElement.lang || "en",
    };
  }

  function pushTrackingEvent(eventName, element, action) {
    var context = getTrackingContext(element);
    var placement = element ? element.getAttribute("data-autoagora-facebook-placement") : "browse_banner";

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: eventName,
      facebook_group_placement: placement || "unknown",
      facebook_group_action: action,
      facebook_group_page_type: context.pageType,
      facebook_group_campaign: context.campaign,
      facebook_group_language: context.language,
    });
  }

  function trackClick(element, action) {
    pushTrackingEvent(
      "autoagora_facebook_group_click",
      element,
      action || element.getAttribute("data-autoagora-facebook-action") || "join"
    );
  }

  function getBannerDismissedUntil() {
    try {
      var storedValue = parseInt(window.localStorage.getItem(bannerDismissalKey), 10) || 0;
      if (storedValue) {
        return storedValue;
      }
    } catch (error) {
      // Fall through to the cookie used when storage is unavailable.
    }

    var cookiePrefix = bannerDismissalKey + "=";
    var cookieValue = document.cookie
      .split(";")
      .map(function (part) {
        return part.trim();
      })
      .find(function (part) {
        return part.indexOf(cookiePrefix) === 0;
      });

    return cookieValue ? parseInt(cookieValue.slice(cookiePrefix.length), 10) || 0 : 0;
  }

  function rememberBannerDismissal() {
    var expiresAt = Date.now() + bannerDismissalDays * 24 * 60 * 60 * 1000;
    var maxAge = bannerDismissalDays * 24 * 60 * 60;

    try {
      window.localStorage.setItem(bannerDismissalKey, String(expiresAt));
    } catch (error) {
      // The cookie below remains as a fallback when storage is unavailable.
    }

    document.cookie =
      bannerDismissalKey +
      "=" +
      expiresAt +
      "; Max-Age=" +
      maxAge +
      "; Path=/; SameSite=Lax" +
      (window.location.protocol === "https:" ? "; Secure" : "");
  }

  function setCampaignBannerHeight(banner) {
    document.documentElement.style.setProperty(
      "--autoagora-facebook-banner-height",
      Math.ceil(banner.getBoundingClientRect().height) + "px"
    );
  }

  function hideCampaignBanner(banner) {
    banner.hidden = true;
    document.body.classList.remove("autoagora-facebook-banner-visible");
    document.documentElement.style.removeProperty("--autoagora-facebook-banner-height");
  }

  function revealCampaignBanner(banner) {
    banner.hidden = false;
    document.body.classList.add("autoagora-facebook-banner-visible");
    setCampaignBannerHeight(banner);

    if (window.ResizeObserver) {
      var observer = new ResizeObserver(function () {
        if (!banner.hidden) {
          setCampaignBannerHeight(banner);
        }
      });
      observer.observe(banner);
    }

    pushTrackingEvent("autoagora_facebook_group_impression", banner, "impression");
  }

  function initializeCampaignBanner() {
    var banner = document.querySelector("[data-autoagora-facebook-campaign-banner]");
    if (!banner) {
      return;
    }

    if (document.querySelector("#email-verification-notification, .email-verification-notice")) {
      banner.remove();
      return;
    }

    if (getBannerDismissedUntil() > Date.now()) {
      banner.remove();
      return;
    }

    var reveal = function () {
      window.removeEventListener("scroll", reveal);
      revealCampaignBanner(banner);
    };

    if (window.scrollY > 24) {
      reveal();
    } else {
      window.addEventListener("scroll", reveal, { passive: true, once: true });
    }

    banner.addEventListener("click", function (event) {
      var dismissButton = event.target.closest("[data-autoagora-facebook-banner-dismiss]");
      if (dismissButton) {
        pushTrackingEvent("autoagora_facebook_group_dismiss", banner, "dismiss");
        rememberBannerDismissal();
        hideCampaignBanner(banner);
        return;
      }

      if (event.target.closest("[data-autoagora-facebook-placement='browse_banner']")) {
        rememberBannerDismissal();
        window.setTimeout(function () {
          hideCampaignBanner(banner);
        }, 0);
      }
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
    initializeCampaignBanner();
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
