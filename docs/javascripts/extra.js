// Hexagonal Maker Bundle - Extra JavaScript

document.addEventListener('DOMContentLoaded', function() {
  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // Add copy feedback to code blocks
  document.querySelectorAll('.md-clipboard').forEach(button => {
    button.addEventListener('click', function() {
      const icon = this.querySelector('svg');
      if (icon) {
        icon.style.color = '#10B981';
        setTimeout(() => {
          icon.style.color = '';
        }, 2000);
      }
    });
  });

  // Highlight current navigation item
  const currentPath = window.location.pathname;
  document.querySelectorAll('.md-nav__link').forEach(link => {
    if (link.getAttribute('href') === currentPath) {
      link.classList.add('md-nav__link--active');
    }
  });

  // Add external link icons
  document.querySelectorAll('a[href^="http"]').forEach(link => {
    if (!link.hostname.includes('github.io')) {
      link.setAttribute('target', '_blank');
      link.setAttribute('rel', 'noopener noreferrer');
    }
  });

  // Add badges to maker commands in tables
  document.querySelectorAll('code').forEach(code => {
    const text = code.textContent;
    if (text.startsWith('make:hexagonal:')) {
      code.style.background = 'linear-gradient(135deg, #6366F1 0%, #4F46E5 100%)';
      code.style.color = 'white';
      code.style.padding = '0.125rem 0.375rem';
      code.style.borderRadius = '0.1875rem';
      code.style.fontWeight = '600';
    }
  });
});

// Add Google Analytics (optional)
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
