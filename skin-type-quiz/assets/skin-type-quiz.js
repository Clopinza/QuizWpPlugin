(function () {
  function initQuiz(quiz) {
    quiz.classList.add('stq-js');
    var form = quiz.querySelector('.stq-form');
    if (!form) return;

    var steps = quiz.querySelectorAll('.stq-step');
    var progressBar = quiz.querySelector('.stq-progress-bar');
    var nextButton = quiz.querySelector('.stq-next');
    var prevButton = quiz.querySelector('.stq-prev');
    var submitButton = quiz.querySelector('.stq-submit');
    var resultBox = quiz.querySelector('.stq-result');
    var currentStep = 0;

    function updateStep() {
      steps.forEach(function (step, index) {
        step.style.display = index === currentStep ? 'block' : 'none';
      });

      if (progressBar) {
        var progress = ((currentStep + 1) / steps.length) * 100;
        progressBar.style.width = progress + '%';
      }

      prevButton.disabled = currentStep === 0;
      nextButton.style.display = currentStep === steps.length - 1 ? 'none' : 'inline-block';
      submitButton.style.display = currentStep === steps.length - 1 ? 'inline-block' : 'none';
    }

    function currentStepValid() {
      var inputs = steps[currentStep].querySelectorAll('input[type="radio"]');
      return Array.prototype.some.call(inputs, function (input) {
        return input.checked;
      });
    }

    nextButton.addEventListener('click', function () {
      if (!currentStepValid()) {
        alert('Seleziona una risposta per continuare.');
        return;
      }
      currentStep = Math.min(currentStep + 1, steps.length - 1);
      updateStep();
    });

    prevButton.addEventListener('click', function () {
      currentStep = Math.max(currentStep - 1, 0);
      updateStep();
    });

    form.addEventListener('submit', function (event) {
      if (!window.STQQuiz) return;
      event.preventDefault();

      var formData = new FormData(form);
      formData.append('action', 'stq_submit_quiz');
      formData.append('nonce', window.STQQuiz.nonce);

      fetch(window.STQQuiz.ajaxUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (payload) {
          if (payload.success) {
            resultBox.innerHTML = '<strong>Risultato:</strong> ' + payload.data.result_title + '<p>' + payload.data.result_description + '</p>';
            form.reset();
            currentStep = 0;
            updateStep();
          } else {
            resultBox.innerHTML = '<span class="stq-error">' + payload.message + '</span>';
          }
        })
        .catch(function () {
          resultBox.innerHTML = '<span class="stq-error">Errore inatteso. Riprova.</span>';
        });
    });

    updateStep();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var quizzes = document.querySelectorAll('.stq-quiz');
    quizzes.forEach(initQuiz);
  });
})();
