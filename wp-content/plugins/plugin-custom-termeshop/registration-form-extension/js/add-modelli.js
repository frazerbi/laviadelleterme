document.addEventListener("DOMContentLoaded", function () {
    // Select the form element
    const fieldAltriModelli = document.getElementById('altri_modelli');

    // Check if the form element exists
    if (!fieldAltriModelli) {
        console.log('Form not found.');
        return;
    }

    // Function to duplicate fields
    let index = 1; // Initialize the index
    function duplicateFields() {
        // Clone the elements with the specified class names
        const clonedModello = document.getElementsByClassName('elementor-field-group-modello')[0].cloneNode(true);
        const clonedMatricola = document.getElementsByClassName('elementor-field-group-matricola')[0].cloneNode(true);
        const clonedNumero = document.getElementsByClassName('elementor-field-group-numero')[0].cloneNode(true);

        // Update the IDs and names of cloned fields with the current index
        clonedModello.id = 'elementor-field-group-modello_' + index;
        clonedMatricola.id = 'elementor-field-group-matricola_' + index;
        clonedNumero.id = 'elementor-field-group-numero_' + index;

        clonedModello.name = 'elementor-field-group-modello_' + index;
        clonedMatricola.name = 'elementor-field-group-matricola_' + index;
        clonedNumero.name = 'elementor-field-group-numero_' + index;

        // Add the index to the input field inside the clonedModello
        const inputField = clonedModello.querySelector('input'); // Assuming it's an input element
        if (inputField) {
            inputField.id = 'form-field-modello_' + index; // Update the ID with the current index
            inputField.name = 'form_fields[modello_'+index+']'; // Update the ID with the current index
        }

        // Set a 30% width to the cloned divs
        clonedModello.classList.add('custom-width-altri-modelli');
        clonedMatricola.classList.add('custom-width-altri-modelli');
        clonedNumero.classList.add('custom-width-altri-modelli');

        // Increment the index for the next set of cloned fields
        index++;

        const removeButtonContainer = document.createElement('div');
        removeButtonContainer.classList.add('remove-button-container');


        // Create a remove button with a "minus" icon
        const removeButton = document.createElement('button');
        removeButton.classList.add('remove-button');
        removeButton.innerHTML = '<span class="minus-icon">-</span>';
        removeButton.addEventListener('click', function () {
            fieldAltriModelli.removeChild(clonedModello);
            fieldAltriModelli.removeChild(clonedMatricola);
            fieldAltriModelli.removeChild(clonedNumero);
            fieldAltriModelli.removeChild(removeButtonContainer);
            fieldAltriModelli.removeChild(removeButton);
        });

        // Append the button to the container div
        removeButtonContainer.appendChild(removeButton);

        fieldAltriModelli.appendChild(clonedModello);
        fieldAltriModelli.appendChild(clonedMatricola);
        fieldAltriModelli.appendChild(clonedNumero);
        fieldAltriModelli.appendChild(removeButtonContainer);

        
    }

    // Attach the click event handler to the existing "Duplicate Fields" button
    const duplicateButton = document.getElementById('duplicateButton');
    if (duplicateButton) {
        duplicateButton.addEventListener('click', duplicateFields);
    } else {
        console.log('Button not found.');
    }
});
