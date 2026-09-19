// Development only: bun tools/sync-rules.ts ../petrovich-rs
import { resolve } from 'node:path';
const source = resolve(process.argv[2] ?? '../petrovich-rs');
const target = resolve(import.meta.dir, '../resources');
for (const name of ['rules', 'gender']) {
    const data = Bun.YAML.parse(await Bun.file(`${source}/src/${name}.yml`).text());
    await Bun.write(`${target}/${name}.json`, JSON.stringify(data, null, 2) + '\n');
}
console.log(`Rules imported from ${source}`);
