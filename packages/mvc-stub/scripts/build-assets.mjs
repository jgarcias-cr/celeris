import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { cp, mkdir } from 'node:fs/promises';
import * as esbuild from 'esbuild';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(__dirname, '..');
const sourceRoot = path.join(projectRoot, 'resources');
const outputRoot = path.join(projectRoot, 'public', 'assets');

const args = new Set(process.argv.slice(2));
const watch = args.has('--watch');
const isProd = args.has('--prod');

const entries = [
   {
      entry: path.join(sourceRoot, 'js', 'app.js'),
      outfile: path.join(outputRoot, 'js', 'app.min.js'),
   },
   {
      entry: path.join(sourceRoot, 'css', 'app.css'),
      outfile: path.join(outputRoot, 'css', 'app.min.css'),
   },
];

const commonOptions = {
   bundle: true,
   legalComments: 'none',
   logLevel: 'info',
   minify: isProd,
   sourcemap: isProd ? false : 'linked',
   target: ['es2018'],
};

async function ensureOutputTree() {
   await Promise.all([
      mkdir(path.join(outputRoot, 'css'), { recursive: true }),
      mkdir(path.join(outputRoot, 'js'), { recursive: true }),
      mkdir(path.join(outputRoot, 'images'), { recursive: true }),
   ]);
}

async function copyImages() {
   const imageSource = path.join(sourceRoot, 'images');
   const imageTarget = path.join(outputRoot, 'images');

   try {
      await cp(imageSource, imageTarget, {
         recursive: true,
         force: true,
         filter: (source) => path.basename(source) !== '.gitkeep',
      });
   } catch (error) {
      if (error && error.code === 'ENOENT') {
         return;
      }

      throw error;
   }
}

async function buildAll() {
   await Promise.all(
      entries.map(({ entry, outfile }) =>
         esbuild.build({
            ...commonOptions,
            entryPoints: [entry],
            outfile,
         }),
      ),
   );
}

async function watchAll() {
   const contexts = await Promise.all(
      entries.map(({ entry, outfile }) =>
         esbuild.context({
            ...commonOptions,
            entryPoints: [entry],
            outfile,
         }),
      ),
   );

   await Promise.all(contexts.map((ctx) => ctx.watch()));
   await copyImages();

   process.on('SIGINT', async () => {
      await Promise.all(contexts.map((ctx) => ctx.dispose()));
      process.exit(0);
   });
}

async function main() {
   await ensureOutputTree();

   if (watch) {
      await watchAll();
      return;
   }

   await buildAll();
   await copyImages();
}

main().catch((error) => {
   console.error('[assets] Build failed:', error);
   process.exit(1);
});
