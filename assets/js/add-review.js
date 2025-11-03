document.getElementById('user_id').addEventListener('change', function () {
    const userId = this.value;
    const courseId = document.getElementById('course_id').value;
    const reviewBox = document.getElementById('existingReview');

    if (!userId || !courseId) return;

    fetch('../components/check-review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ user_id: userId, course_id: courseId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.exists) {
            reviewBox.classList.remove('d-none');
            reviewBox.innerHTML = `<strong>Existing Review:</strong><br>Rating: ${'⭐'.repeat(data.rating)}<br>Comment: ${data.content}`;
        } else {
            reviewBox.classList.add('d-none');
            reviewBox.innerHTML = '';
        }
    });
});