const { join } = require('path');

module.exports = {
    cacheDirectory: join('/var/www/.cache', 'puppeteer'),
    executablePath: '/usr/bin/google-chrome',
    args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-crash-reporter'
    ]
};