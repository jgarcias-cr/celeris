(() => {
   'use strict';

   document.documentElement.classList.add('js');

   const staggered = Array.from(document.querySelectorAll('[data-stagger-item], [data-contact-link]'));
   staggered.forEach((item, index) => {
      item.style.setProperty('--stagger-index', String(index));
   });

   const table = document.querySelector('[data-contacts-table]');
   if (!table) {
      return;
   }

   const rows = Array.from(table.querySelectorAll('[data-contact-row]'));
   const pageSizeSelect = document.querySelector('[data-page-size]');
   const pagination = document.querySelector('[data-pagination]');
   const prevButton = document.querySelector('[data-pagination-prev]');
   const nextButton = document.querySelector('[data-pagination-next]');
   const pageInfo = document.querySelector('[data-pagination-info]');

   if (!pageSizeSelect || !pagination || !prevButton || !nextButton || !pageInfo || rows.length === 0) {
      return;
   }

   let pageSize = Number.parseInt(pageSizeSelect.value, 10) || 10;
   let currentPage = 1;

   const render = () => {
      const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
      currentPage = Math.min(currentPage, totalPages);

      const start = (currentPage - 1) * pageSize;
      const end = start + pageSize;

      rows.forEach((row, index) => {
         row.style.display = index >= start && index < end ? 'table-row' : 'none';
      });

      prevButton.disabled = currentPage <= 1;
      nextButton.disabled = currentPage >= totalPages;
      pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
   };

   pageSizeSelect.addEventListener('change', () => {
      pageSize = Number.parseInt(pageSizeSelect.value, 10) || 10;
      currentPage = 1;
      render();
   });

   prevButton.addEventListener('click', () => {
      if (currentPage <= 1) {
         return;
      }

      currentPage -= 1;
      render();
   });

   nextButton.addEventListener('click', () => {
      const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
      if (currentPage >= totalPages) {
         return;
      }

      currentPage += 1;
      render();
   });

   render();
})();
