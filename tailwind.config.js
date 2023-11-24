/** @type {import('tailwindcss').Config} */

let coreTheme = {
  extend: {
    fontFamily: {
      "nb-font": ['"Lato"', "sans-serif"],
    },
    typography: {
      DEFAULT: {
        css: { "max-width": "none" },
      },
      xl: {
        css: { "line-height": 1.6 },
      },
    },
    colors: {
      primary: "#0074D9",
      cnormal: "#0074D9",
      clink: "#0074D9",
      cdark: "#0068c3",
      cdarkest: "#005198",
      clight: "#80baec",
      secondary: '#80baec'
    },
  }
};

let extTheme = null;

try {
  extTheme = require("./ext/tailwind.theme.js");
} catch (error) {
  
}

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
  theme: extTheme || coreTheme,
  plugins: [
    require("@tailwindcss/typography"),
    require("tw-elements/dist/plugin"),
  ],
};
