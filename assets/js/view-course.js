document.addEventListener('DOMContentLoaded', function () {
  const enrollForm = document.getElementById('enrollForm');
  const demoModal = document.getElementById('demoModal');
  const demoIframeContainer = demoModal.querySelector('.modal-body');

  // 🎥 Show secure preview for YouTube or MP4
  window.showDemo = function (videoUrl) {
    let embed = '';
    const isYouTube = /(?:youtube\.com|youtu\.be)/i.test(videoUrl);
    const isMP4 = /\.mp4(\?.*)?$/i.test(videoUrl);

    if (isYouTube) {
      const embedUrl = videoUrl
        .replace("watch?v=", "embed/")
        .replace("youtu.be/", "youtube.com/embed/")
        + '?rel=0&modestbranding=1&showinfo=0&controls=1';

      embed = `
        <iframe 
          src="${embedUrl}" 
          frameborder="0" 
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
          allowfullscreen 
          style="width:100%; height:450px; border-radius: 0.5rem;">
        </iframe>`;
    } else if (isMP4) {
      embed = `
        <video controls style="width:100%; height:450px; border-radius: 0.5rem;">
          <source src="${videoUrl}" type="video/mp4">
          Your browser does not support the video tag.
        </video>`;
    } else {
      embed = `
        <iframe 
          src="${videoUrl}" 
          frameborder="0" 
          allowfullscreen 
          style="width:100%; height:450px; border-radius: 0.5rem;">
        </iframe>`;
    }

    // Inject and show modal
    demoIframeContainer.innerHTML = embed;
    const modal = new bootstrap.Modal(demoModal);
    modal.show();

    // Clean on close
    demoModal.addEventListener('hidden.bs.modal', () => {
      demoIframeContainer.innerHTML = `
        <iframe id="demoIframe" class="w-100" height="450" frameborder="0" allowfullscreen></iframe>
      `;
    }, { once: true });
  };

  // 🚀 Enroll confirmation prompt
  if (enrollForm) {
    enrollForm.addEventListener('submit', function (e) {
      e.preventDefault();
      Swal.fire({
        title: 'Confirm Enrollment?',
        text: 'Are you sure you want to enroll in this course?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Enroll Me!',
        cancelButtonText: 'Cancel',
        reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          enrollForm.submit();
        }
      });
    });
  }
});