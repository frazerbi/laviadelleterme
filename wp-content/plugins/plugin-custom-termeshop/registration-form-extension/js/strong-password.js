document.addEventListener("DOMContentLoaded", function () {
  const passwordInput = document.getElementById("form-field-password_register");

  // Create the strength message <div> element
  const strengthText = document.createElement("div");

  if (!passwordInput || !strengthText) {
    //console.error("Element with specified ID not found.");
    return;
  }

  passwordInput.setAttribute("current-password", "true");


  strengthText.id = "password-strength-text";

  // Append the strength message <div> element to the form
  passwordInput.parentNode.appendChild(strengthText);

  function checkPasswordStrength(password) {
    const strongRegex = /^(?=.*[a-zàèéìòù])(?=.*[A-ZÀÈÉÌÒÙ])(?=.*\d)[A-Za-zàèéìòùÀÈÉÌÒÙ\d]{8,}$/;
    
    const criteria = {
      length: {
        text: "Almeno 8 caratteri",
        check: password.length >= 8,
      },
      lowercase: {
        text: "Almeno una lettera minuscola",
        check: /[a-zàèéìòù]/.test(password),
      },
      uppercase: {
        text: "Almeno una lettera maiuscola",
        check: /[A-ZÀÈÉÌÒÙ]/.test(password),
      },
      digit: {
        text: "Almeno un numero",
        check: /\d/.test(password),
      },
    };

    let message = "La password deve soddisfare i seguenti criteri:<br><ul>";

    for (const criterion in criteria) {
      message += `<li style="color: ${criteria[criterion].check ? 'green' : 'red'}">${criteria[criterion].text}</li>`;
    }

    message += "</ul>";

    strengthText.innerHTML = message;

    const isStrongPassword = strongRegex.test(password);

    if (isStrongPassword) {
      strengthText.style.color = "green";
    } else {
      strengthText.style.color = "red";
    }
  }

  // Add an event listener to the password input field to update criteria color
  passwordInput.addEventListener("input", function () {
    checkPasswordStrength(this.value);
  });

  // Initialize the strength message
  checkPasswordStrength(passwordInput.value);
});