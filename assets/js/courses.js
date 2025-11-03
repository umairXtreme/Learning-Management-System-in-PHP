document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('courseFilters');
  const grid = document.getElementById('coursesGrid');
  const pagin = document.getElementById('paginationLinks');

  function loadCourses(page = 1) {
    const formData = new FormData(form);
    formData.append('page', page);
    fetch(`ajax/fetch-courses.php?${new URLSearchParams(formData)}`)
      .then(res => res.json())
      .then(data => {
        grid.innerHTML = data.courses;
        pagin.innerHTML = data.pagination;
        bindPagination(); // Just pagination bind now
      });
  }

  function bindPagination() {
    document.querySelectorAll('.page-link').forEach(link => {
      link.onclick = e => {
        e.preventDefault();
        loadCourses(link.dataset.page);
      };
    });
  }

  form.onsubmit = e => {
    e.preventDefault();
    loadCourses();
  };

  loadCourses();
});