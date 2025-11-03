document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('.student-sidebar');
  const toggleBtn = document.getElementById('sidebarToggle');

  // Toggle sidebar
  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
  });

  // Auto-close on outside click (for mobile)
  document.addEventListener('click', function (e) {
    if (
      !sidebar.contains(e.target) &&
      !toggleBtn.contains(e.target) &&
      window.innerWidth <= 768
    ) {
      sidebar.classList.remove('active');
    }
  });
});