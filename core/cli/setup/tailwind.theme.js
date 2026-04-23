import defaultTheme from 'tailwindcss/defaultTheme';

export const daisyuiThemes = [
  {
    light: {
      "primary": "#0074D9",
      "primary-content": "#ffffff",
      "secondary": "#80baec",
      "secondary-content": "#ffffff",
      "accent": "#0074D9",
      "accent-content": "#ffffff",
      "neutral": "#2a323c",
      "neutral-content": "#ffffff",
      "base-100": "#ffffff",
      "base-200": "#f9fafb",
      "base-300": "#e5e7eb",
      "base-content": "#1f2937",
      "info": "#3abff8",
      "success": "#36d399",
      "warning": "#fbbd23",
      "error": "#f87272",
    },
  },
];

let appTheme = {
  extend: {
    fontFamily: {
      "primary": ['Inter var', ...defaultTheme.fontFamily.sans],
      "sans": ['Inter var', ...defaultTheme.fontFamily.sans],
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
      cbar: "#0074D9",
      clink: "#0074D9",
      cdark: "#0068c3",
      cdarkest: "#005198",
      clight: "#80baec",
      secondary: '#80baec'
    },
  }
};

export default appTheme;
