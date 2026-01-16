const esbuild = require('esbuild');
const postcss = require('postcss');
const prefixSelector = require('postcss-prefix-selector');
const fs = require('fs');
const path = require('path');

const isWatch = process.argv.includes('--watch');

// Plugin esbuild per processare CSS con PostCSS
const postcssPlugin = {
  name: 'postcss-prefix',
  setup(build) {
    build.onEnd(async (result) => {
      // Trova il file CSS generato
      const cssFile = path.join(__dirname, 'assets/js/dist/booking-form.min.css');

      if (fs.existsSync(cssFile)) {
        const css = fs.readFileSync(cssFile, 'utf8');

        // Processa con PostCSS per aggiungere il prefix
        const processed = await postcss([
          prefixSelector({
            prefix: '.skianet-booking-wrapper',
            transform: function (prefix, selector, prefixedSelector) {
              // Non prefixare :root, html, body
              if (selector.match(/^(html|body|:root)/)) {
                return selector;
              }
              return prefixedSelector;
            }
          })
        ]).process(css, { from: cssFile, to: cssFile });

        // Sovrascrivi il file CSS con la versione prefixata
        fs.writeFileSync(cssFile, processed.css);
        console.log('✅ CSS prefixato con successo!');
      }
    });
  }
};

const buildOptions = {
  entryPoints: ['assets/js/src/booking-form.js'],
  bundle: true,
  minify: true,
  sourcemap: true,
  target: ['es2017'], // Supporta browser moderni ma non troppo recenti
  outdir: 'assets/js/dist', // Cambiato da outfile a outdir per gestire CSS separato
  entryNames: '[name].min',
  format: 'iife', // Immediately Invoked Function Expression (compatibile con WordPress)
  globalName: 'BookingForm',
  platform: 'browser',
  loader: {
    '.css': 'css', // Gestisce i file CSS
  },
  plugins: [postcssPlugin],
};

async function build() {
  try {
    if (isWatch) {
      const ctx = await esbuild.context(buildOptions);
      await ctx.watch();
      console.log('👀 Watching for changes...');
    } else {
      await esbuild.build(buildOptions);
      console.log('✅ Build completata!');
    }
  } catch (error) {
    console.error('❌ Build fallita:', error);
    process.exit(1);
  }
}

build();
