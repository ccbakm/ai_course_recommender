// ===== NEURAL AURORA - ENHANCED =====

// ===== BACKGROUND ORBS (injected into body) =====
document.addEventListener('DOMContentLoaded', () => {
  // Dark + light mode orbs (1-3 always; 4-5 light-only via CSS)
  ['bg-orb-1', 'bg-orb-2', 'bg-orb-3', 'bg-orb-4', 'bg-orb-5'].forEach(cls => {
    const orb = document.createElement('div');
    orb.className = 'bg-orb ' + cls;
    document.body.appendChild(orb);
  });

  // Floating rings — light mode only (CSS hides them in dark mode)
  for (let i = 1; i <= 5; i++) {
    const ring = document.createElement('div');
    ring.className = `light-ring light-ring-${i}`;
    document.body.appendChild(ring);
  }

  // Circuit-node diamonds — light mode only
  for (let i = 1; i <= 9; i++) {
    const sparkle = document.createElement('div');
    sparkle.className = `light-sparkle light-sparkle-${i}`;
    document.body.appendChild(sparkle);
  }

  // Scan-line sweep — light mode only
  const scanLine = document.createElement('div');
  scanLine.className = 'light-scan-line';
  document.body.appendChild(scanLine);
});

// ===== SCROLL REVEAL (clean CSS-class approach) =====
document.addEventListener('DOMContentLoaded', () => {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('revealed');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

  document.querySelectorAll('.card, .feature-card, .step').forEach(el => {
    if (el.dataset.reveal === undefined) {
      el.dataset.reveal = '';
      observer.observe(el);
    }
  });
});

// ===== NAVBAR HIDE ON SCROLL =====
const createNavBar = () => {
  let lastScroll = 0;
  let ticking = false;
  const navbar = document.querySelector('.navbar');

  if (navbar) {
    window.addEventListener('scroll', () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          const currentScroll = window.pageYOffset;
          if (currentScroll > lastScroll && currentScroll > 300) {
            navbar.style.transform = 'translateY(-100%)';
          } else {
            navbar.style.transform = 'translateY(0)';
          }
          lastScroll = currentScroll;
          ticking = false;
        });
        ticking = true;
      }
    }, { passive: true });
  }
};

document.addEventListener('DOMContentLoaded', createNavBar);

// ===== SMOOTH HASH SCROLL =====
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', (e) => {
      const target = link.getAttribute('href');
      if (target === '#') return;
      const section = document.querySelector(target);
      if (section) {
        e.preventDefault();
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
});

// ===== LIGHTWEIGHT PARTICLE SYSTEM =====
class LightweightParticleSystem {
  constructor(containerId = 'particle-field') {
    this.container = document.getElementById(containerId);
    if (!this.container) return;
    this.particleCount = window.innerWidth > 768 ? 15 : 5;
    this.init();
  }

  init() {
    for (let i = 0; i < this.particleCount; i++) this.createParticle();
  }

  createParticle() {
    const particle = document.createElement('div');
    particle.className = 'particle';
    const size = Math.random() * 4 + 1;
    particle.style.cssText = `
      width: ${size}px; height: ${size}px;
      left: ${Math.random() * 100}%; top: ${Math.random() * 100}%;
      animation: float ${Math.random() * 20 + 30}s linear ${Math.random() * 20}s infinite;
      will-change: transform; position: absolute;
      background: rgba(124,77,255,0.4); border-radius: 50%;
    `;
    this.container.appendChild(particle);
  }
}

document.addEventListener('DOMContentLoaded', () => new LightweightParticleSystem());

// ===== NEURAL ANIMATION (used by recommender form) =====
function triggerNeuralAnimation() {
  const overlay = document.createElement('div');
  overlay.id = 'neural-overlay';
  overlay.style.cssText = `
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(3, 11, 30, 0.85); backdrop-filter: blur(8px);
    z-index: 9999; display: flex; align-items: center; justify-content: center;
    animation: fadeIn 0.3s ease;
  `;
  overlay.innerHTML = `
    <div style="text-align: center; z-index: 10000; position: relative;">
      <div style="
        width: 60px; height: 60px;
        border: 3px solid rgba(0, 229, 255, 0.2);
        border-top-color: #00e5ff; border-right-color: #7c4dff;
        border-radius: 50%; animation: neuralSpin 1.5s linear infinite;
        margin: 0 auto 20px; box-shadow: 0 0 20px rgba(0, 229, 255, 0.3);
      "></div>
      <p style="color: #00e5ff; font-size: 15px; font-weight: 500; letter-spacing: 1px; margin: 0 0 8px;">
        Neural pathways activating
      </p>
      <p style="color: #7c4dff; font-size: 12px; opacity: 0.6; margin: 0;">
        Analyzing your learning profile…
      </p>
    </div>
  `;
  document.body.appendChild(overlay);
  return overlay;
}

function removeNeuralAnimation() {
  const overlay = document.getElementById('neural-overlay');
  if (overlay) {
    overlay.style.animation = 'fadeOut 0.3s ease forwards';
    setTimeout(() => overlay.remove(), 300);
  }
}

// ===== GLOBAL KEYFRAMES =====
const globalAnimations = document.createElement('style');
globalAnimations.textContent = `
  @keyframes neuralSpin { to { transform: rotate(360deg); } }
  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
  @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
  @keyframes float {
    0%, 100% { transform: translateY(0) translateX(0); }
    50% { transform: translateY(-20px) translateX(10px); }
  }
`;
document.head.appendChild(globalAnimations);
