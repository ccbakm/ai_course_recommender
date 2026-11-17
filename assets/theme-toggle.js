// ===== THEME TOGGLE SYSTEM (Enhanced for Light Mode Fix) =====

class ThemeToggle {
  constructor() {
    this.darkTheme = {
      '--brand': '#7c4dff',
      '--brand2': '#00e5ff',
      '--bg-deep': '#030B1E',
      '--bg-dark': '#0b1020',
      '--bg-darker': '#0f172a',
      '--bg-card': '#1e293b',
      '--text-primary': '#e7e9ee',
      '--text-secondary': '#c9d1e4',
      '--text-muted': '#94a3b8',
      '--border-color': 'rgba(255, 255, 255, 0.06)'
    };

    this.lightTheme = {
      '--brand': '#7c4dff',
      '--brand2': '#00b4d8',
      '--bg-deep': '#f0f4ff',
      '--bg-dark': '#f8faff',
      '--bg-darker': '#eef1fa',
      '--bg-card': '#ffffff',
      '--text-primary': '#111827',
      '--text-secondary': '#374151',
      '--text-muted': '#6b7280',
      '--border-color': 'rgba(124, 77, 255, 0.12)',
      '--glow-sm': 'rgba(124, 77, 255, 0.12)',
      '--glow-lg': 'rgba(124, 77, 255, 0.2)'
    };

    this.currentTheme = localStorage.getItem('theme') || 'dark';
    this.init();
  }

  init() {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.setup());
    } else {
      this.setup();
    }
  }

  setup() {
    this.applyTheme(this.currentTheme);
    this.setupBackgroundGradient();
    this.createToggleButton();
  }

  createToggleButton() {
    if (document.getElementById('themeToggle')) return;

    const button = document.createElement('button');
    button.id = 'themeToggle';
    button.className = 'theme-toggle-btn';
    button.innerHTML = this.currentTheme === 'dark' ? '☀️' : '🌙';
    button.title = this.currentTheme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode';
    button.setAttribute('type', 'button');
    button.style.transition = 'transform 0.2s ease, box-shadow 0.2s ease';

    button.addEventListener('click', () => this.toggleTheme());
    document.body.appendChild(button);
  }

  toggleTheme() {
    this.currentTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('theme', this.currentTheme);
    this.applyTheme(this.currentTheme);
    this.setupBackgroundGradient();
    this.updateToggleButton();
  }

  applyTheme(theme) {
    const colors = theme === 'dark' ? this.darkTheme : this.lightTheme;
    Object.keys(colors).forEach(key => {
      document.documentElement.style.setProperty(key, colors[key]);
    });

    // 🔹 Add/remove body class for CSS targeting
    document.body.classList.toggle('light', theme === 'light');
    document.body.classList.toggle('dark', theme === 'dark');

    // Optional smooth fade
    document.body.style.transition = 'background 0.4s ease, color 0.4s ease';
  }

  setupBackgroundGradient() {
    if (this.currentTheme === 'dark') {
      document.body.style.backgroundSize = '';
      document.body.style.backgroundAttachment = 'fixed';
      document.body.style.background =
        'linear-gradient(135deg, #030B1E 0%, #1B0F35 50%, #0b1020 100%)';
    } else {
      // Electric circuit-grid — backgroundAttachment left as default 'scroll'
      // (fixed attachment repaints on every scroll frame = major perf hit)
      document.body.style.background = [
        'linear-gradient(rgba(124,77,255,0.055) 1px, transparent 1px)',
        'linear-gradient(90deg, rgba(0,229,255,0.04) 1px, transparent 1px)',
        'linear-gradient(135deg, #f0f5ff 0%, #f5f0ff 50%, #f0f8ff 100%)'
      ].join(', ');
      document.body.style.backgroundSize = '52px 52px, 52px 52px, 100% 100%';
      document.body.style.backgroundAttachment = '';
    }
  }

  updateToggleButton() {
    const button = document.getElementById('themeToggle');
    if (!button) return;
    button.innerHTML = this.currentTheme === 'dark' ? '☀️' : '🌙';
    button.title =
      this.currentTheme === 'dark'
        ? 'Switch to Light Mode'
        : 'Switch to Dark Mode';
  }
}

// Initialize theme toggle
new ThemeToggle();
