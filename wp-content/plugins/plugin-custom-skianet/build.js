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

// Configurazione per booking-form-code.js (form con codice)
const bookingFormCodeBuildOptions = {
  entryPoints: ['assets/js/src/booking-only-form.js'],
  bundle: true,
  minify: true,
  sourcemap: true,
  target: ['es2017'],
  outdir: 'assets/js/dist',
  entryNames: '[name].min',
  format: 'iife',
  globalName: 'BookingFormCode',
  platform: 'browser',
  loader: {
    '.css': 'css',
  },
  plugins: [postcssPlugin],
};

async function build() {
  try {
    if (isWatch) {
      // Watch mode - crea context per entrambi i file
      const ctx1 = await esbuild.context(buildOptions);
      const ctx2 = await esbuild.context(bookingFormCodeBuildOptions);
      
      await Promise.all([
        ctx1.watch(),
        ctx2.watch()
      ]);
      
      console.log('👀 Watching for changes...');
      console.log('   - booking-form.js');
      console.log('   - booking-form-code.js');
    } else {
      // Build mode - compila entrambi i file
      await Promise.all([
        esbuild.build(buildOptions),
        esbuild.build(bookingFormCodeBuildOptions)
      ]);
      
      console.log('✅ Build completata!');
      console.log('   - booking-form.min.js');
      console.log('   - booking-form-code.min.js');
    }
  } catch (error) {
    console.error('❌ Build fallita:', error);
    process.exit(1);
  }
}
build();
