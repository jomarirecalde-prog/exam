import fs from 'fs';
import path from 'path';

const source = path.join('public', 'vendor', 'livewire');
const target = path.join('public', 'livewire');
const files = ['livewire.min.js', 'livewire.min.js.map', 'livewire.js', 'manifest.json'];

if (!fs.existsSync(source)) {
    console.warn('Livewire vendor assets missing; skipping copy to public/livewire.');
    process.exit(0);
}

fs.mkdirSync(target, { recursive: true });

for (const file of files) {
    fs.copyFileSync(path.join(source, file), path.join(target, file));
}

console.log('Livewire assets copied to public/livewire');
