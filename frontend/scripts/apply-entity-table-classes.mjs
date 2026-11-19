/**
 * Adds Decisions-style entity table classes across module list tables.
 * Skips specialty tables (accommodation-stays, copy preview, etc.).
 */
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve('src/modules')
const skipClassHints = [
  'accommodation-stays-table',
  'accommodation-table-surface',
  'app-entity-table',
]

function walk(dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) walk(full, out)
    else if (entry.name.endsWith('.vue')) out.push(full)
  }
  return out
}

function shouldSkipTable(openTag) {
  return skipClassHints.some((hint) => openTag.includes(hint))
}

function ensureClass(openTag, className) {
  if (openTag.includes(className)) return openTag
  if (/class="/.test(openTag)) {
    return openTag.replace(/class="/, `class="${className} `)
  }
  if (/class='/.test(openTag)) {
    return openTag.replace(/class='/, `class='${className} `)
  }
  return openTag.replace(/<table\b/, `<table class="${className}"`)
}

function ensureWrapperClass(content) {
  // Common card wrappers that immediately wrap a scroll/table region.
  const patterns = [
    /(class=")([^"]*overflow-(?:hidden|x-auto)[^"]*rounded-2xl[^"]*border[^"]*brand-border[^"]*")/g,
    /(class=")([^"]*rounded-2xl[^"]*border[^"]*brand-border[^"]*overflow-(?:hidden|x-auto)[^"]*")/g,
  ]
  let next = content
  for (const re of patterns) {
    next = next.replace(re, (full, prefix, classes) => {
      if (classes.includes('app-entity-table-card')) return full
      if (classes.includes('accommodation-stays') || classes.includes('accommodation-table-surface')) {
        return full
      }
      return `${prefix}app-entity-table-card ${classes}`
    })
  }
  return next
}

const files = walk(root)
let changedFiles = 0
let tableHits = 0

for (const file of files) {
  let text = fs.readFileSync(file, 'utf8')
  if (!text.includes('<table')) continue

  const original = text
  text = text.replace(/<table\b[^>]*>/g, (openTag) => {
    if (shouldSkipTable(openTag)) return openTag
    tableHits += 1
    return ensureClass(openTag, 'app-entity-table')
  })
  text = ensureWrapperClass(text)

  if (text !== original) {
    fs.writeFileSync(file, text)
    changedFiles += 1
    console.log('updated', path.relative(process.cwd(), file))
  }
}

console.log(`Done. files=${changedFiles} tablesTouched≈${tableHits}`)
