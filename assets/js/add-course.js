document.addEventListener('DOMContentLoaded', function () {
    tinymce.init({
        selector: 'textarea.rich',
        height: 250,
        menubar: false,
        branding: false, // ✅ Remove TinyMCE credit
        plugins: 'lists link image code',
        toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | link image code',
        setup: function (editor) {
            editor.on('change', function () {
                tinymce.triggerSave(); // Syncs content to <textarea>
            });
        }
    });

    let lessonCount = 1;
    window.addLesson = function () {
        if (lessonCount >= 50) return;

        const container = document.getElementById("lesson-container");
        const lessonRow = document.createElement("div");
        lessonRow.className = "row g-2 mb-2";

        lessonRow.innerHTML = `
            <div class="col-md-6">
                <input type="text" name="lessons[${lessonCount}][title]" class="form-control" placeholder="Lesson Title" required>
            </div>
            <div class="col-md-6 d-flex">
                <input type="url" name="lessons[${lessonCount}][url]" class="form-control" placeholder="Lesson Video URL" required>
                <button type="button" class="btn btn-danger ms-2" onclick="this.closest('.row').remove()">🗑</button>
            </div>
        `;
        container.appendChild(lessonRow);
        lessonCount++;
    };

    document.querySelector('form').addEventListener('submit', function () {
        tinymce.triggerSave();
    });
});
document.addEventListener("DOMContentLoaded", () => {
  const select = document.getElementById('categorySelect');
  const customInput = document.getElementById('customCategory');

  select.addEventListener('change', () => {
    if (select.value === '__custom__') {
      customInput.classList.remove('d-none');
      customInput.setAttribute('required', 'required');
    } else {
      customInput.classList.add('d-none');
      customInput.removeAttribute('required');
    }
  });
});