(() => {
   'use strict';

   document.documentElement.classList.add('js');

   const links = Array.from(document.querySelectorAll('[data-contact-link]'));
   links.forEach((link, index) => {
      link.style.setProperty('--stagger-index', String(index));
   });
})();
