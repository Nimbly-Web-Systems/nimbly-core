/** @type {import('tailwindcss').Config} */

const theme = require('./ext/tailwind.theme.js'); 

module.exports = {
  darkMode: 'class',
  content: [
    "./core/uri/**/*.tpl",
    "./core/uri/.modal/**/*.tpl",
    "./core/tpl/**/*.tpl",
    "./core/modules/**/*.tpl",
    "./ext/uri/**/*.tpl",
    "./ext/uri/.modal/**/*.tpl",
    "./ext/tpl/**/*.tpl",
    "./ext/modules/**/*.tpl",
    "./node_modules/tw-elements/dist/js/**/*.js",
  ],
  theme: theme,
  plugins: [
    require("@tailwindcss/typography"),
    require("tw-elements/dist/plugin"),
  ],
};
