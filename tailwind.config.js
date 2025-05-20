// tailwind.config.js
import theme from './ext/tailwind.theme.js';
import typography from '@tailwindcss/typography';
import twElements from 'tw-elements/dist/plugin.js';

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    './core/uri/**/*.tpl',
    './core/uri/.modal/**/*.tpl',
    './core/tpl/**/*.tpl',
    './core/modules/**/*.tpl',
    './ext/uri/**/*.tpl',
    './ext/uri/.modal/**/*.tpl',
    './ext/tpl/**/*.tpl',
    './ext/modules/**/*.tpl',
    './node_modules/tw-elements/dist/js/**/*.js',
  ],
  theme,
  plugins: [typography, twElements],
};