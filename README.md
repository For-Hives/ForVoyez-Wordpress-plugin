# forvoyez-auto-alt-text-for-images

*this is a plugin for the [Forvoyez](https://forvoyez.com) platform*

## Table of Contents

- [Description](#description)
- [Installation](#installation)
- [BrowserSync Configuration](#browsersync-configuration)
- [Usage](#usage)

## Description

This plugin automatically generates alt text for images in your content.
it's a wordpress plugin.

## Installation

### Prerequisites

- PHP >= 8.2
- Composer
- Node.js and npm

### Steps

2. **Install PHP Dependencies**:

```sh
composer install
```

3. **Install Node.js Dependencies**:

```sh
npm install
```

## BrowserSync Configuration

To use BrowserSync with your WordPlate project, follow these steps:

1. **Create a BrowserSync Configuration File**:
   Create a file named `bs-config.cjs` at the root of your project with the following content:

```js
module.exports = {
    proxy: "localhost:8000", // Replace with your PHP server port
    files: ["public/**/*.php", "resources/**/*.php", "public/**/*.css", "public/**/*.js"],
    notify: false
};
```

2. **Start the PHP Server**:
   In your terminal, run the following command to start the PHP server:

```sh
php -S localhost:8000 -t public
```

3. **Start BrowserSync**:
   In another terminal, run the following command to start BrowserSync:

```sh
browser-sync start --config bs-config.cjs
```

## Usage

Once both servers are running, BrowserSync will monitor the specified files in `bs-config.cjs` and automatically reload the page in your browser when changes are detected.

### Project Structure Example

Your project structure should look something like this:

```
├── bs-config.cjs
├── composer.json
├── package.json
├── public
│   ├── index.php
│   ├── wp-config.php
│   └── ...
├── resources
│   ├── views
│   └── ...
└── ...
```
