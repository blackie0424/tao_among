import { describe, it, expect } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const currentDir = path.dirname(fileURLToPath(import.meta.url))
const projectRoot = path.resolve(currentDir, '../../..')

describe('repository cleanup', () => {
  it('removes confirmed legacy deployment and tooling files', () => {
    const paths = [
      '.ebextensions',
      '.platform',
      '.kiro',
      'vercel.json',
      '.vercelignore',
      'api/index.php',
    ]

    for (const target of paths) {
      expect(fs.existsSync(path.join(projectRoot, target))).toBe(false)
    }
  })
})
