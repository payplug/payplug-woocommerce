# Install
## Dependencies
If you’re using Linux, you’ll want to have the required dependencies installed on your system.
### Ubuntu/Debian
```
$apt-get install libgtk2.0-0 libgtk-3-0 libgbm-dev libnotify-dev libgconf-2-4 libnss3 libxss1 libasound2 libxtst6 xauth xvfb
```

## NPM
```
# Install cypress dependences
$ npm install
```

# Usage

## Config
After create the `cypress.env` file, open the file and change the config.

```
# Copy cypress.env.json from cypress.env.example.json
$ cp cypress.env.example.json cypress.env.json
```
## Run
```
# Opening cypress panel
$ ./node_modules/.bin/cypress open

# Run cypress on terminal
$ ./node_modules/.bin/cypress run
```
### Parameters
```
# Config path
-C 'cypress/config/pt.json'

# Run a single spec
--spec 'cypress/integration/application/catalog/CAT02_products.spec.js'

# Run on a specific browser
--browser firefox

# Run cypress headless
--headless

# Record the tests on cypress/videos
--record

# Integrate the tests with cypress dashboard
--key d934a693-1372-4c3b-8796-274a6d402d27
```

## Structure
\> config -> Cypress config used by language or environment\
\> fixture -> Files used by cypress\
\> helpers -> Custom helpers\
\> integration -> Integration tests (Tests that will be displayed on the cypress panel)\
\> pageObjects -> Page objects classes\
\> plugins -> Cypress plugins
\> support -> Cypress support folder (Mainly used to create custom commands in `commands.js`)

# Creating new tests
1. Create a new test file on `integration\application`;
2. Import and init all Page objects and helpers;
3. Inside the beforeEach, load all fixture json objects with the `data` helper;
```
data.load(array|string)
```
4. to use the loaded objects on test section use `this.object`;

## Best practices
1. When creating a new interface on the system. try adding `data-cy` to elements to make selecting elements easier in Cypress;
2. Try to reuse as many page objects as you can.

# Useful links
[Cypress docs](https://docs.cypress.io/) \
[Cypress install](https://docs.cypress.io/guides/getting-started/installing-cypress.html) \
[Cypress best practices](https://docs.cypress.io/guides/references/best-practices.html) 