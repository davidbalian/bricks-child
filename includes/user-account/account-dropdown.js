document.addEventListener("DOMContentLoaded", function () {
  const accountDisplay = document.querySelector(".account-display-logged-in");
  if (!accountDisplay) return;

  const userNameDisplay = accountDisplay.querySelector(".user-name-display"); // The parent span
  const dropdown = accountDisplay.querySelector(".account-dropdown");
  // const chevron = userNameDisplay ? userNameDisplay.querySelector('.chevron-icon') : null; // Don't need direct chevron ref

  // Toggle dropdown on name click
  if (userNameDisplay) {
    // Check if userNameDisplay exists
    userNameDisplay.addEventListener("click", function (e) {
      e.stopPropagation();
      // userNameDisplay.classList.toggle('active'); // Keep if needed for other styling
      userNameDisplay.classList.toggle("rotate"); // Toggle rotate class on the parent span
      dropdown.classList.toggle("show");
    });
  }

  // Close dropdown when clicking outside
  document.addEventListener("click", function (e) {
    if (accountDisplay.contains(e.target)) return; // Don't close if click is inside
    if (userNameDisplay && userNameDisplay.classList.contains("rotate")) {
      // Check parent for rotate
      // userNameDisplay.classList.remove('active');
      userNameDisplay.classList.remove("rotate"); // Remove rotate class from parent span
      dropdown.classList.remove("show");
    }
  });

  // Prevent dropdown from closing when clicking inside it
  if (dropdown) {
    // Check if dropdown exists
    dropdown.addEventListener("click", function (e) {
      e.stopPropagation();
    });
  }
});
