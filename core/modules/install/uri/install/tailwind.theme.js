import defaultTheme from 'tailwindcss/defaultTheme';

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