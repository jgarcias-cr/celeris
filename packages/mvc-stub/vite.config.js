import { cp, mkdir, readdir, rm } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const resourcesDir = path.resolve(__dirname, 'resources');
const imagesDir = path.join(resourcesDir, 'images');
const outputDir = path.resolve(__dirname, 'public', 'assets');
const outputImagesDir = path.join(outputDir, 'images');

async function copyImages() {
   await rm(outputImagesDir, { force: true, recursive: true });
   await mkdir(outputImagesDir, { recursive: true });

   if (!existsSync(imagesDir)) {
      return;
   }

   await cp(imagesDir, outputImagesDir, {
      recursive: true,
      force: true,
      filter: (source) => path.basename(source) !== '.gitkeep',
   });
}

function collectWatchPaths(directory) {
   if (!existsSync(directory)) {
      return [];
   }

   const paths = [directory];

   const visit = async (currentDirectory) => {
      const entries = await readdir(currentDirectory, { withFileTypes: true });
      for (const entry of entries) {
         const absolutePath = path.join(currentDirectory, entry.name);
         paths.push(absolutePath);

         if (entry.isDirectory()) {
            await visit(absolutePath);
         }
      }
   };

   return visit(directory).then(() => paths);
}

function copyResourceImagesPlugin() {
   return {
      name: 'copy-resource-images',
      async buildStart() {
         const watchPaths = await collectWatchPaths(imagesDir);
         for (const watchPath of watchPaths) {
            this.addWatchFile(watchPath);
         }
      },
      async closeBundle() {
         await copyImages();
      },
   };
}

export default defineConfig(({ mode }) => ({
   publicDir: false,
   build: {
      copyPublicDir: false,
      emptyOutDir: true,
      outDir: outputDir,
      minify: mode === 'production',
      sourcemap: mode !== 'production',
      rollupOptions: {
         input: {
            app: path.join(resourcesDir, 'js', 'app.js'),
            styles: path.join(resourcesDir, 'css', 'app.css'),
         },
         output: {
            entryFileNames: (chunk) => (chunk.name === 'app' ? 'js/app.min.js' : 'js/[name].min.js'),
            chunkFileNames: 'js/chunks/[name]-[hash].js',
            assetFileNames: (asset) => {
               if (asset.name?.endsWith('.css')) {
                  return 'css/app.min.css';
               }

               return 'assets/[name]-[hash][extname]';
            },
         },
      },
   },
   plugins: [copyResourceImagesPlugin()],
}));
