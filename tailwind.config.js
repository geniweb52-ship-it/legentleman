/**
 * Thème de la boutique et de l'administration.
 * Après toute modification de classes dans les fichiers PHP/JS : npm run build:css
 * (ou npm run watch:css pendant le développement).
 */
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./*.php', './includes/**/*.php', './admin/**/*.php', './assets/app.js'],
  // Classe construite dynamiquement en PHP (accueil : nombre de catégories).
  safelist: [{ pattern: /^grid-cols-[1-6]$/, variants: ['lg'] }],
  theme: {
    extend: {
      colors: {
        // forest / forest-dark / terra : surchargées par les thèmes de fête (variables CSS, voir includes/themes.php).
        cream: '#F8F5EF', sand: '#F8F5EF', sidebar: '#1F3526',
        forest: 'rgb(var(--c-forest, 46 74 54) / <alpha-value>)',
        'forest-dark': 'rgb(var(--c-forest-dark, 35 58 42) / <alpha-value>)',
        terra: 'rgb(var(--c-terra, 180 83 42) / <alpha-value>)',
        ink: '#1D1D1B', line: '#E7E1D6', muted: '#6E6A62', gold: '#C8A24A', indigo: '#2E4A36',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        display: ['"Libre Caslon Text"', 'Georgia', 'serif'],
      },
      maxWidth: { site: '1320px' },
    },
  },
};
