(function() {
  'use strict';

  // Mobile nav toggle
  var toggle = document.querySelector('.nav-toggle');
  var navList = document.querySelector('.nav-list');
  if (toggle && navList) {
    toggle.addEventListener('click', function() {
      navList.classList.toggle('open');
    });
  }

  // Close nav on link click (mobile)
  navList && navList.addEventListener('click', function(e) {
    if (e.target.tagName === 'A') {
      navList.classList.remove('open');
    }
  });

  // Modal system
  var modals = document.querySelectorAll('.modal-overlay');
  var modalTriggers = document.querySelectorAll('[data-modal]');
  var modalCloses = document.querySelectorAll('.modal-close');

  modalTriggers.forEach(function(trigger) {
    trigger.addEventListener('click', function(e) {
      e.preventDefault();
      var targetId = this.getAttribute('data-modal');
      var modal = document.getElementById(targetId);
      if (modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
      }
    });
  });

  modalCloses.forEach(function(btn) {
    btn.addEventListener('click', function() {
      var modal = this.closest('.modal-overlay');
      modal.classList.remove('open');
      document.body.style.overflow = '';
    });
  });

  modals.forEach(function(modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === this) {
        this.classList.remove('open');
        document.body.style.overflow = '';
      }
    });
  });

  // Hero slider
  var slider = document.querySelector('.hero-slider');
  if (slider) {
    var slides = slider.querySelectorAll('.hero-slide');
    var dotsContainer = slider.querySelector('.slider-dots');
    var current = 0;

    function showSlide(index) {
      slides.forEach(function(s, i) {
        s.style.display = i === index ? 'flex' : 'none';
      });
      if (dotsContainer) {
        var dots = dotsContainer.querySelectorAll('span');
        dots.forEach(function(d, i) {
          d.classList.toggle('active', i === index);
        });
      }
    }

    if (slides.length > 1) {
      // Create dots
      if (dotsContainer) {
        for (var i = 0; i < slides.length; i++) {
          var dot = document.createElement('span');
          if (i === 0) dot.classList.add('active');
          dot.addEventListener('click', function() {
            var idx = Array.prototype.indexOf.call(dotsContainer.querySelectorAll('span'), this);
            current = idx;
            showSlide(current);
          });
          dotsContainer.appendChild(dot);
        }
      }

      showSlide(0);

      // Auto-rotate
      setInterval(function() {
        current = (current + 1) % slides.length;
        showSlide(current);
      }, 5000);
    } else if (slides.length === 1) {
      showSlide(0);
    }
  }

  // Counter animation
  var counters = document.querySelectorAll('.counter');
  if (counters.length > 0) {
    function animateCounters() {
      counters.forEach(function(counter) {
        var target = parseFloat(counter.getAttribute('data-count'));
        var isFloat = target % 1 !== 0;
        var duration = 2000;
        var start = 0;
        var startTime = null;

        function step(timestamp) {
          if (!startTime) startTime = timestamp;
          var progress = Math.min((timestamp - startTime) / duration, 1);
          var currentVal = progress * target;
          counter.textContent = isFloat ? currentVal.toFixed(2) : Math.floor(currentVal);
          if (progress < 1) {
            requestAnimationFrame(step);
          } else {
            counter.textContent = isFloat ? target.toFixed(2) : target;
          }
        }

        requestAnimationFrame(step);
      });
    }

    var counterSection = document.querySelector('.stats-section');
    if (counterSection) {
      var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
          if (entry.isIntersecting) {
            animateCounters();
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.3 });
      observer.observe(counterSection);
    }
  }

})();
