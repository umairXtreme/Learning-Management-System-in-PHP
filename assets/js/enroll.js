// /assets/js/enroll.js

document.addEventListener("DOMContentLoaded", () => {
    const enrollForm = document.getElementById("enrollForm");
  
    if (enrollForm) {
      enrollForm.addEventListener("submit", function (e) {
        const courseId = document.getElementById("course_id").value;
  
        if (!courseId || isNaN(courseId)) {
          e.preventDefault();
          alert("❌ Invalid course ID!");
        }
      });
    }
  });  