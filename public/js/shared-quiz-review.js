document.addEventListener('DOMContentLoaded', () => {
    const questions = Array.isArray(window.sharedQuizReviewQuestions)
        ? window.sharedQuizReviewQuestions
        : [];

    questions.forEach(question => {
        addQuestionBlock(
            question.question ?? '',
            Array.isArray(question.options) ? question.options : ['', '', '', ''],
            Number.parseInt(question.correct, 10) || 0
        );
    });

    if (!document.getElementById('questions-builder')?.children.length) {
        addNewQuestion();
    }

    const gradeSelect = document.getElementById('q-grade');
    const classOptions = [...document.querySelectorAll('[data-class-option]')];
    const noMatchingClass = document.getElementById('review-no-matching-class');
    const submitLabel = document.getElementById('copy-assign-label');
    const timeLimitGroup = document.getElementById('review-time-limit');
    const timeLimitInput = document.getElementById('review-time-limit-input');

    function updateSubmissionMode() {
        const selectedCount = classOptions.filter(option => {
            const checkbox = option.querySelector('input[type="checkbox"]');
            return checkbox && !checkbox.disabled && checkbox.checked;
        }).length;
        const willAssign = selectedCount > 0;

        if (submitLabel) {
            submitLabel.textContent = willAssign ? 'Save Copy & Assign' : 'Save Copy';
        }
        if (timeLimitInput) {
            timeLimitInput.disabled = !willAssign;
            timeLimitInput.required = willAssign;
        }
        timeLimitGroup?.classList.toggle('opacity-40', !willAssign);
    }

    function filterClassesByGrade() {
        const grade = Number(gradeSelect?.value);
        let matchingClasses = 0;

        classOptions.forEach(option => {
            const checkbox = option.querySelector('input[type="checkbox"]');
            const matches = Number(option.dataset.grade) === grade;

            option.classList.toggle('hidden', !matches);
            if (checkbox) {
                checkbox.disabled = !matches;
                if (!matches) checkbox.checked = false;
            }
            if (matches) matchingClasses++;
        });

        noMatchingClass?.classList.toggle('hidden', matchingClasses > 0);
        updateSubmissionMode();
    }

    classOptions.forEach(option => {
        option.querySelector('input[type="checkbox"]')?.addEventListener('change', updateSubmissionMode);
    });
    gradeSelect?.addEventListener('change', filterClassesByGrade);
    filterClassesByGrade();
});
