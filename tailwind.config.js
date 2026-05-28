import theme, { daisyuiThemes = [] } from "./ext/tailwind.theme.js";
import typography from "@tailwindcss/typography";
import daisyui from "daisyui";

// Keep the existing ext/tailwind.theme.js API while emitting daisyUI v5 CSS variables.
function to_daisyui_v5_theme(theme_values) {
  const result = {
    "--color-primary-content": "#ffffff",
    "--color-secondary-content": "#ffffff",
    "--color-accent-content": "#ffffff",
    "--color-neutral-content": "#ffffff",
    "--color-base-100": "#ffffff",
    "--color-base-200": "#f9fafb",
    "--color-base-300": "#e5e7eb",
    "--color-base-content": "#1f2937",
    "--color-info-content": "#ffffff",
    "--color-success-content": "#ffffff",
    "--color-warning-content": "#1f2937",
    "--color-error-content": "#ffffff",
    "--radius-selector": "0.5rem",
    "--radius-field": "0.25rem",
    "--radius-box": "0.5rem",
    "--size-selector": "0.25rem",
    "--size-field": "0.25rem",
    "--border": "1px",
    "--depth": "1",
    "--noise": "0",
  };

  Object.entries(theme_values).forEach(([key, value]) => {
    result["--color-" + key] = value;
  });

  return result;
}

function nimbly_daisyui_themes({ addBase }) {
  daisyuiThemes.forEach((theme_config, index) => {
    Object.entries(theme_config).forEach(([theme_name, theme_values]) => {
      let selector = `[data-theme=${theme_name}],:root:has(input.theme-controller[value=${theme_name}]:checked)`;

      if (index === 0) {
        selector = `:where(:root),${selector}`;
      }

      addBase({
        [selector]: {
          "color-scheme": "light",
          ...to_daisyui_v5_theme(theme_values),
        },
      });
    });
  });
}

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: "class",
  content: [
    "./core/uri/**/*.tpl",
    "./core/uri/.modal/**/*.tpl",
    "./core/tpl/**/*.tpl",
    "./core/lib/**/*.tpl",
    "./core/modules/**/*.tpl",
    "./ext/uri/**/*.tpl",
    "./ext/uri/.modal/**/*.tpl",
    "./ext/lib/**/*.tpl",
    "./ext/tpl/**/*.tpl",
    "./ext/modules/**/*.tpl",
  ],
  theme,
  plugins: [typography, daisyui({ themes: [] }), nimbly_daisyui_themes],
};
