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
    const submit = document.getElementById('copy-assign-submit');

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
        if (submit) {
            submit.disabled = matchingClasses === 0;
            submit.classList.toggle('opacity-40', matchingClasses === 0);
            submit.classList.toggle('cursor-not-allowed', matchingClasses === 0);
        }
    }

    gradeSelect?.addEventListener('change', filterClassesByGrade);
    filterClassesByGrade();
});
