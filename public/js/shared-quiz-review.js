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
    const form = document.getElementById('shared-quiz-copy-form');
    const confirmTitle = document.getElementById('confirm-shared-quiz-title');
    const confirmSummary = document.getElementById('confirm-shared-quiz-summary');
    const confirmMeta = document.getElementById('confirm-shared-quiz-meta');
    const confirmClasses = document.getElementById('confirm-shared-quiz-classes');
    const confirmClassList = document.getElementById('confirm-shared-quiz-class-list');
    const confirmButton = document.getElementById('confirm-shared-quiz-submit');
    const confirmButtonLabel = document.getElementById('confirm-shared-quiz-button-label');
    let confirmationApproved = false;

    function selectedClassOptions() {
        return classOptions.filter(option => {
            const checkbox = option.querySelector('input[type="checkbox"]');
            return checkbox && !checkbox.disabled && checkbox.checked;
        });
    }

    function updateSubmissionMode() {
        const selectedCount = selectedClassOptions().length;
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

    function showSaveConfirmation() {
        const selectedClasses = selectedClassOptions();
        const willAssign = selectedClasses.length > 0;
        const topic = document.getElementById('q-topic')?.value.trim() || 'this quiz';
        const grade = gradeSelect?.value || '—';
        const questionCount = document.querySelectorAll('.question-block').length;
        const timeLimit = timeLimitInput?.value || '—';
        const classLabel = selectedClasses.length === 1 ? '1 class' : `${selectedClasses.length} classes`;

        if (confirmTitle) {
            confirmTitle.textContent = willAssign ? 'Save Copy & Assign?' : 'Save Quiz Copy?';
        }
        if (confirmSummary) {
            confirmSummary.textContent = willAssign
                ? `A personal copy of “${topic}” will be saved and assigned to ${classLabel}.`
                : `A personal copy of “${topic}” will be saved to My Quizzes without a class assignment.`;
        }
        if (confirmMeta) {
            confirmMeta.textContent = willAssign
                ? `Grade ${grade} · ${questionCount} questions · ${timeLimit} seconds per question`
                : `Grade ${grade} · ${questionCount} questions · No class assignment`;
        }
        if (confirmButtonLabel) {
            confirmButtonLabel.textContent = willAssign ? 'Save Copy & Assign' : 'Save Copy';
        }

        if (confirmClassList) {
            confirmClassList.replaceChildren();
            selectedClasses.forEach(option => {
                const item = document.createElement('li');
                item.className = 'flex items-center gap-2';
                item.textContent = option.dataset.className || 'Selected class';
                confirmClassList.appendChild(item);
            });
        }
        confirmClasses?.classList.toggle('hidden', !willAssign);
        openModal('confirmSharedQuizModal');
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
    form?.addEventListener('submit', event => {
        if (confirmationApproved) return;

        event.preventDefault();
        showSaveConfirmation();
    });
    confirmButton?.addEventListener('click', () => {
        confirmationApproved = true;
        closeModal('confirmSharedQuizModal');
        form?.requestSubmit();
        setTimeout(() => {
            confirmationApproved = false;
        }, 0);
    });
    gradeSelect?.addEventListener('change', filterClassesByGrade);
    filterClassesByGrade();
});
