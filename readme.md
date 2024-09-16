# Nimbly Framework

Nimbly is a lightweight PHP micro framework for ultra-fast web development. It allows 100% customized applications, with handcrafted HTML, CSS, and JS files, minimal footprint, and easy-to-use features.

## Features
- Built-in template engine with shortcodes.
- Flexible routing.
- Data management with JSON-based NoSQL.
- Automatic JSON API for data resources.
- User management (users, roles, groups, access rights).
- Uses Tailwind CSS, component library, and Alpine.js out of the box.
- Inline editing with Medium Editor.
- Secure form handling
- Extendable application layers: core and ext.

## Quick Start

### Using Devcontainer (VSCode)
1. Run the devcontainer in VSCode.

### Manual Installation
1. Run the following commands:
    ```bash
    cd ~/dev # or your project root dir
    git clone git@github.com:Nimbly-Web-Systems/nimbly-core.git name-of-my-project
    cd name-of-my-project
    git checkout 1.0
    npm install
    npm run build
    cd docker && docker-compose up -d && cd ..
    ```

2. Open your browser and visit `http://localhost/install.php`.