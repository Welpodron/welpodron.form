import typescript from '@rollup/plugin-typescript';
import { nodeResolve } from '@rollup/plugin-node-resolve';

export default {
  input: './install/packages/welpodron.form/ts/index.ts',
  output: {
    dir: 'tests',
  },
  watch: {
    include: ['**/ts/**/*'],
    exclude: ['**/*.test.ts'],
  },
  plugins: [
    nodeResolve(),
    typescript({
      compilerOptions: {
        module: 'ESNext',
        target: 'ESNext',
        moduleResolution: 'Bundler',
      },
      include: ['**/ts/**/*'],
      exclude: ['**/*.test.ts'],
    }),
  ],
};
