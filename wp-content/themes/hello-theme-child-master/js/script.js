document.addEventListener("DOMContentLoaded", function() {
  if (window.location.pathname === "/promozioni-speciali/") {
    let link = document.querySelector(".page-id-10925 #pwbox-10925");
    let link_1 = document.querySelector(".page-id-10925 .ppw-password-input.ppw-pcp-pf-password-input");

    if (link) {
      link.setAttribute("type", "text");
    }
    if (link_1) {
      link_1.setAttribute("type", "text");
    }

    function normalizeInput(event) {
      event.target.value = event.target.value.toLowerCase().trim();
    }

    if (link) {
      link.addEventListener("input", normalizeInput);
    }
    if (link_1) {
      link_1.addEventListener("input", normalizeInput);
    }
  }
});


if(window.location.pathname == '/associazione-albergatori-della-valle-daosta/') {
  let passField = document.querySelector('.page-id-1326 input.ppw-password-input.ppw-pcp-pf-password-input');
  
  if (typeof passField === 'object' && passField !== null ) {
    passField.setAttribute('type','text');
  }

  jQuery('.page-id-1326 input.ppw-password-input.ppw-pcp-pf-password-input').on('input', function(evt) {
      jQuery(this).val(function(_, val) {
        return val.toLowerCase().trim();
      });
    });
}


document.addEventListener('DOMContentLoaded', function () {
  const menuToggle = document.querySelector('.icon-menu-mobile-toggle');
  const menuToggleSticky = document.querySelector('.icon-menu-mobile-toggle-sticky');
  const offCanvasContainers = document.querySelectorAll('[id^="off-canvas-"]');
  
  // Variabile globale per tracciare lo stato del menu
  let isMenuOpen = false;

  // Verifica lo stato iniziale del menu basato su aria-hidden
  function checkInitialState() {
    if (offCanvasContainers && offCanvasContainers.length > 0) {
      // Controlliamo il primo elemento (o puoi scegliere una logica diversa)
      isMenuOpen = offCanvasContainers[0].getAttribute('aria-hidden') === 'false';
      updateMenuState(isMenuOpen);
    }
  }

  function updateMenuState(isOpen) {
    isMenuOpen = isOpen;
    
    // Aggiorna entrambi i toggle in base allo stato
    if (menuToggle) {
      if (isOpen) {
        menuToggle.classList.add('open');
      } else {
        menuToggle.classList.remove('open');
      }
    }
    
    if (menuToggleSticky) {
      if (isOpen) {
        menuToggleSticky.classList.add('open');
      } else {
        menuToggleSticky.classList.remove('open');
      }
    }
  }

  // IMPORTANTE: NON blocchiamo l'evento predefinito o la sua propagazione
  // Lasciamo che Elementor gestisca l'apertura/chiusura del menu

  // Aggiunge eventi click per entrambi i toggle
  if (menuToggle && offCanvasContainers.length > 0) {
    menuToggle.addEventListener('click', () => {
      // Qui NON usiamo event.preventDefault() o event.stopPropagation()
      // Aggiorniamo solo lo stato visivo dell'icona dopo un breve ritardo
      setTimeout(() => {
        checkInitialState();
      }, 100);
    });
  }
  
  if (menuToggleSticky && offCanvasContainers.length > 0) {
    menuToggleSticky.addEventListener('click', () => {
      // Qui NON usiamo event.preventDefault() o event.stopPropagation()
      // Aggiorniamo solo lo stato visivo dell'icona dopo un breve ritardo
      setTimeout(() => {
        checkInitialState();
      }, 100);
    });
  }
  
  // Osserva cambiamenti nell'attributo aria-hidden
  if (offCanvasContainers.length > 0 && 'MutationObserver' in window) {
    // Per ogni contenitore off-canvas, aggiungi un observer
    offCanvasContainers.forEach(container => {
      const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.type === 'attributes' && 
              (mutation.attributeName === 'aria-hidden' || mutation.attributeName === 'class')) {
            // Controlla se lo stato è cambiato
            const newState = container.getAttribute('aria-hidden') === 'false';
            
            // Aggiorna lo stato solo se è diverso da quello attuale
            if (newState !== isMenuOpen) {
              updateMenuState(newState);
            }
          }
        });
      });
      
      observer.observe(container, { 
        attributes: true, 
        attributeFilter: ['aria-hidden', 'class'] 
      });
    });
  }
});

document.addEventListener("DOMContentLoaded", function () {
  // Select the second header
  const secondHeader = document.getElementById('header_main_sub_container');
  if(secondHeader) {
     // Add a scroll event listener
      window.addEventListener('scroll', () => {
        if (window.scrollY < 250) {
            // When at the top of the page, hide the second header
            secondHeader.classList.add('hidden');
            secondHeader.classList.remove('show');
        } else {
            // After scrolling beyond 150px, show the header
            secondHeader.classList.remove('hidden');
            secondHeader.classList.add('show');
        }
    });
  }
});