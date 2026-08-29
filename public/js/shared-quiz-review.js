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
    const submitLabel = document.getElementById('shared-assign-label');
    const timeLimitGroup = document.getElementById('review-time-limit');
    const timeLimitInput = document.getElementById('review-time-limit-input');
    const scheduleGroup = document.getElementById('review-schedule');
    const form = document.getElementById('shared-quiz-assignment-form');
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

        if (submitLabel) {
            submitLabel.textContent = selectedCount > 0
                ? `Assign to ${selectedCount === 1 ? '1 Class' : `${selectedCount} Classes`}`
                : 'Select a Class to Assign';
        }
        if (timeLimitInput) {
            timeLimitInput.disabled = false;
            timeLimitInput.required = true;
        }
        timeLimitGroup?.classList.remove('opacity-40');
        scheduleGroup?.classList.remove('opacity-40');
        scheduleGroup?.querySelectorAll('input').forEach(input => { input.disabled = false; });
    }

    function showSaveConfirmation() {
        const selectedClasses = selectedClassOptions();
        if (selectedClasses.length === 0) {
            if (noMatchingClass) {
                noMatchingClass.textContent = 'Select at least one matching class before assigning this quiz.';
                noMatchingClass.classList.remove('hidden');
            }
            noMatchingClass?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        const topic = document.getElementById('q-topic')?.value.trim() || 'this quiz';
        const grade = gradeSelect?.value || '—';
        const questionCount = document.querySelectorAll('.question-block').length;
        const timeLimit = timeLimitInput?.value || '—';
        const classLabel = selectedClasses.length === 1 ? '1 class' : `${selectedClasses.length} classes`;

        if (confirmTitle) {
            confirmTitle.textContent = 'Assign Quiz to Classes?';
        }
        if (confirmSummary) {
            confirmSummary.textContent = `“${topic}” will be assigned to ${classLabel}. The shared original will not change.`;
        }
        if (confirmMeta) {
            confirmMeta.textContent = `Grade ${grade} · ${questionCount} questions · ${timeLimit} seconds per question`;
        }
        if (confirmButtonLabel) {
            confirmButtonLabel.textContent = 'Assign to Classes';
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
        confirmClasses?.classList.remove('hidden');
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
