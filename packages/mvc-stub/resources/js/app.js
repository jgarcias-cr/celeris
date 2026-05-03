import Alpine from 'alpinejs';

document.addEventListener('alpine:init', () => {
   Alpine.data('contactsTable', (totalRows = 0) => ({
      currentPage: 1,
      pageSize: 10,
      totalRows,

      get totalPages() {
         return Math.max(1, Math.ceil(this.totalRows / this.pageSize));
      },

      get pageLabel() {
         return `Page ${this.currentPage} of ${this.totalPages}`;
      },

      isVisible(index) {
         const start = (this.currentPage - 1) * this.pageSize;
         const end = start + this.pageSize;
         return index >= start && index < end;
      },

      previousPage() {
         if (this.currentPage > 1) {
            this.currentPage -= 1;
         }
      },

      nextPage() {
         if (this.currentPage < this.totalPages) {
            this.currentPage += 1;
         }
      },

      resetPage() {
         this.currentPage = 1;
      },

      confirmDelete(event, message) {
         if (window.confirm(message)) {
            event.target.submit();
         }
      },
   }));
});

window.Alpine = Alpine;
Alpine.start();
