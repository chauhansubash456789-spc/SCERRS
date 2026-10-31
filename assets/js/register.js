document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("scerrsRegisterForm");
  if (!form) return;

  // Let custom JavaScript validation handle inline UI
  form.setAttribute("novalidate", "true");

  // Input Elements
  const countrySelect = document.getElementById("countrySelect");
  const identityDocInput = document.getElementById("identityDocInput");
  const identityDocLabel = document.getElementById("identityDocLabel");

  const passwordInput = form.querySelector(
    'input[placeholder="Min. 8 characters"]',
  );
  const confirmPasswordInput = form.querySelector(
    'input[placeholder="Re-enter password"]',
  );
  const phoneInput = form.querySelector('input[type="tel"]');
  const emailInput = form.querySelector('input[type="email"]');

  const authoritySection = document.getElementById("authorityFields");
  const roleRadios = form.querySelectorAll('input[name="userRole"]');

  const provinceSelect = document.getElementById("provinceSelect");
  const districtSelect = document.getElementById("districtSelect");
  const municipalitySelect = document.getElementById("municipalitySelect");

  // 0. Cascading Location (Province -> District -> Municipality)
  // Data is embedded in nepal_locations_data.js (assigned to window.NEPAL_LOCATIONS),
  // so no fetch is required and the cascade works reliably.
  const locationData = window.NEPAL_LOCATIONS || null;

  // Province fallback list (matches PHP $provinces array order)
  const fallbackProvinces = [
    "Koshi",
    "Madhesh",
    "Bagmati",
    "Gandaki",
    "Lumbini",
    "Karnali",
    "Sudur Paschim",
  ];

  function resetSelect(select, placeholder, disabled) {
    select.innerHTML = "";
    const ph = document.createElement("option");
    ph.value = "";
    ph.textContent = placeholder;
    select.appendChild(ph);
    select.disabled = !!disabled;
    clearError(select);
  }

  function fillSelect(select, options, placeholder) {
    select.innerHTML = "";
    const ph = document.createElement("option");
    ph.value = "";
    ph.textContent = placeholder;
    select.appendChild(ph);
    options.forEach((opt) => {
      const o = document.createElement("option");
      o.value = opt;
      o.textContent = opt;
      select.appendChild(o);
    });
    select.disabled = false;
    clearError(select);
  }

  // Reset + disable downstream dropdowns (Requirement: clear parent => reset children)
  function resetLocationCascade(fromDistrict) {
    if (fromDistrict !== "district") {
      resetSelect(districtSelect, "Select District", true);
    }
    resetSelect(municipalitySelect, "Select Municipality", true);
  }

  // Populate province dropdown on initial load
  function initProvince() {
    if (provinceSelect) {
      const provinces = locationData
        ? Object.keys(locationData)
        : fallbackProvinces;
      resetSelect(provinceSelect, "Select Province", false);
      fillSelect(provinceSelect, provinces, "Select Province");
      resetLocationCascade();
    }
  }

  window.handleProvinceChange = function () {
    resetLocationCascade("province");
    if (provinceSelect.value && locationData) {
      const districts = locationData[provinceSelect.value] || {};
      fillSelect(districtSelect, Object.keys(districts), "Select District");
    }
    validateField(provinceSelect);
    validateField(districtSelect);
    validateField(municipalitySelect);
  };

  window.handleDistrictChange = function () {
    resetLocationCascade("district");
    if (districtSelect.value && locationData && provinceSelect.value) {
      const districts = locationData[provinceSelect.value] || {};
      const municipalities = districts[districtSelect.value] || [];
      fillSelect(municipalitySelect, municipalities, "Select Municipality");
    }
    validateField(districtSelect);
    validateField(municipalitySelect);
  };

  // Initialize once
  if (provinceSelect) {
    initProvince();
  }

  // 1. Dynamic Country Toggle (Citizenship vs Passport)
  window.handleCountryChange = function () {
    if (countrySelect.value === "Nepal") {
      identityDocLabel.innerText = "Citizenship No. *";
      identityDocInput.placeholder = "e.g. 12-01-78-12345";
    } else {
      identityDocLabel.innerText = "Passport No. *";
      identityDocInput.placeholder = "e.g. A1234567 or 123456789";
    }
    validateField(identityDocInput);
  };

  // 2. Dynamic Role Toggle (Citizen vs Authority)
  window.toggleRoleFields = function () {
    const selectedRole = form.querySelector(
      'input[name="userRole"]:checked',
    ).value;
    const authInputs = authoritySection.querySelectorAll("input, select");

    if (selectedRole === "authority") {
      authoritySection.classList.add("active");
      authInputs.forEach((input) => input.setAttribute("required", "true"));
    } else {
      authoritySection.classList.remove("active");
      authInputs.forEach((input) => {
        input.removeAttribute("required");
        clearError(input);
      });
    }
  };

  if (roleRadios.length) {
    roleRadios.forEach((r) =>
      r.addEventListener("change", () => {
        if (typeof window.toggleRoleFields === "function")
          window.toggleRoleFields();
      }),
    );
  }

  // 3. Validation Logic Helpers
  function showError(input, message) {
    clearError(input);
    input.classList.add("input-error");
    const errorDiv = document.createElement("small");
    errorDiv.className = "error-message";
    errorDiv.innerText = message;
    input.parentNode.appendChild(errorDiv);
  }

  function clearError(input) {
    input.classList.remove("input-error");
    const existingError = input.parentNode.querySelector(".error-message");
    if (existingError) {
      existingError.remove();
    }
  }

  function validateField(input) {
    // Skip disabled selects (these are waitign for a parent selection)
    if (input.disabled) {
      clearError(input);
      return true;
    }

    const val = input.value.trim();

    // Required check
    if (input.hasAttribute("required") && !val) {
      showError(input, "This field is required.");
      return false;
    }
    // Clear any stale error once a value is selected
    if (val) {
      clearError(input);
    }

    // Name fields (First Name, Middle Name, Last Name - No numeric values allowed)
    if (
      (input.name === "first_name" ||
        input.name === "last_name" ||
        input.name === "middle_name") &&
      val
    ) {
      if (/[0-9]/.test(val)) {
        const fieldName =
          input.name === "first_name"
            ? "First name"
            : input.name === "last_name"
              ? "Last name"
              : "Middle name";
        showError(input, `${fieldName} must not contain numeric values.`);
        return false;
      }
    }

    // Email Validation
    if (input.type === "email" && val) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/;
      if (!emailRegex.test(val)) {
        showError(input, "Please enter a valid email address.");
        return false;
      }
    }

    // Phone Number Validation (Nepal Focus)
    if (input.type === "tel" && val) {
      const phoneRegex = /^(\+977)?(98|97|96)\d{8}$/;
      if (
        countrySelect.value === "Nepal" &&
        !phoneRegex.test(val.replace(/\s+/g, ""))
      ) {
        showError(
          input,
          "Enter a valid 10-digit mobile number starting with 98/97/96.",
        );
        return false;
      }
    }

    // Identity Document Check
    if (input.id === "identityDocInput" && val) {
      if (countrySelect.value === "Nepal") {
        // Citizenship format pattern check
        const citizenshipRegex = /^[0-9\/-]{5,20}$/;
        if (!citizenshipRegex.test(val)) {
          showError(input, "Enter a valid Citizenship Number.");
          return false;
        }
      } else {
        // Passport format check (ICAO standard: 1-2 letters + 7 digits, or 8-9 digits)
        const passportRegex = /^(?:[A-Z]{1,2}\d{7}|\d{8,9})$/i;
        if (!passportRegex.test(val)) {
          showError(
            input,
            "Enter a valid Passport Number (e.g. A1234567 or 123456789).",
          );
          return false;
        }
      }
    }

    // Password Length Check
    if (input === passwordInput && val) {
      if (val.length < 8) {
        showError(input, "Password must be at least 8 characters long.");
        return false;
      }
    }

    // Confirm Password Match Check
    if (input === confirmPasswordInput && val) {
      if (val !== passwordInput.value) {
        showError(input, "Passwords do not match.");
        return false;
      }
    }

    clearError(input);
    return true;
  }

  // 4. Real-time Blur/Input Event Listeners
  const allInputs = form.querySelectorAll("input, select");
  allInputs.forEach((input) => {
    input.addEventListener("blur", () => validateField(input));
    input.addEventListener("input", () => {
      if (input.classList.contains("input-error")) {
        validateField(input);
      } else if (
        (input.name === "first_name" ||
          input.name === "last_name" ||
          input.name === "middle_name") &&
        /[0-9]/.test(input.value)
      ) {
        validateField(input);
      }
    });
  });

  // 5. Form Submission Guard
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    let isValid = true;

    // Validate all active inputs
    allInputs.forEach((input) => {
      // Skip hidden authority inputs if citizen role selected
      if (
        input.closest(".authority-only") &&
        !authoritySection.classList.contains("active")
      ) {
        return;
      }
      if (!validateField(input)) {
        isValid = false;
      }
    });

    if (isValid) {
      // Hand off to the server (bypasses this listener, no loop).
      // PHP persists the account and redirects to the login page.
      form.submit();
    } else {
      // Focus on first error element
      const firstError = form.querySelector(".input-error");
      if (firstError) {
        firstError.focus();
        firstError.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    }
  });
});
