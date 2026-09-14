/**
 * SQL statement parser and cursor extractor for Nimbus SQL Console.
 * Handles quotes, comments, semicolons, selections, and cursor positions.
 */

/**
 * Splits SQL text into individual statements, respecting quotes (', ", `) and comments (-- ..., /* ... * /).
 * Returns an array of objects: { sql: string, start: number, end: number }
 */
export function parseSqlStatements(text) {
  if (!text) return []
  const statements = []
  let inSingleQuote = false
  let inDoubleQuote = false
  let inBacktick = false
  let inLineComment = false
  let inBlockComment = false
  let currentStart = -1
  let i = 0
  const len = text.length

  while (i < len) {
    const ch = text[i]
    const nextCh = i + 1 < len ? text[i + 1] : ''

    if (inSingleQuote) {
      if (ch === '\\') { i += 2; continue }
      if (ch === "'") inSingleQuote = false
      i++; continue
    }
    if (inDoubleQuote) {
      if (ch === '\\') { i += 2; continue }
      if (ch === '"') inDoubleQuote = false
      i++; continue
    }
    if (inBacktick) {
      if (ch === '\\') { i += 2; continue }
      if (ch === '`') inBacktick = false
      i++; continue
    }
    if (inLineComment) {
      if (ch === '\n' || ch === '\r') inLineComment = false
      i++; continue
    }
    if (inBlockComment) {
      if (ch === '*' && nextCh === '/') { inBlockComment = false; i += 2; continue }
      i++; continue
    }

    // Single-line comments (-- or #)
    if ((ch === '-' && nextCh === '-') || ch === '#') {
      inLineComment = true; i += (ch === '-' ? 2 : 1); continue
    }
    // Block comments (/* ... */)
    if (ch === '/' && nextCh === '*') {
      inBlockComment = true; i += 2; continue
    }

    // Quotes
    if (ch === "'") {
      if (currentStart === -1) currentStart = i
      inSingleQuote = true; i++; continue
    }
    if (ch === '"') {
      if (currentStart === -1) currentStart = i
      inDoubleQuote = true; i++; continue
    }
    if (ch === '`') {
      if (currentStart === -1) currentStart = i
      inBacktick = true; i++; continue
    }

    // Non-whitespace character marks start of statement
    if (currentStart === -1 && !/\s/.test(ch)) {
      currentStart = i
    }

    // Semicolon statement boundary
    if (ch === ';') {
      if (currentStart !== -1) {
        const stmtText = text.substring(currentStart, i + 1).trim()
        if (stmtText && stmtText !== ';') {
          statements.push({ sql: stmtText, start: currentStart, end: i + 1 })
        }
        currentStart = -1
      }
      i++; continue
    }
    i++
  }

  // Trailing statement without trailing semicolon
  if (currentStart !== -1 && currentStart < len) {
    const stmtText = text.substring(currentStart, len).trim()
    if (stmtText && stmtText !== ';') {
      statements.push({ sql: stmtText, start: currentStart, end: len })
    }
  }

  // If no semicolon statements were found and text contains blank lines:
  if (statements.length <= 1 && !text.includes(';')) {
    const blockRegex = /[^\r\n]+(?:\r?\n(?!\r?\n)[^\r\n]+)*/g
    const blocks = []
    let match
    while ((match = blockRegex.exec(text)) !== null) {
      const trimmed = match[0].trim()
      if (trimmed) {
        blocks.push({
          sql: trimmed,
          start: match.index,
          end: match.index + match[0].length
        })
      }
    }
    if (blocks.length > 1) {
      return blocks
    }
  }

  return statements
}

/**
 * Extracts the target SQL query based on cursor position or active selection in the textarea.
 * 
 * @param {HTMLTextAreaElement|null} textareaEl 
 * @param {string} fullSql 
 * @returns {string} The query to execute
 */
export function extractTargetQuery(textareaEl, fullSql) {
  if (!fullSql || !fullSql.trim()) return ''

  if (textareaEl) {
    const selStart = textareaEl.selectionStart
    const selEnd = textareaEl.selectionEnd

    // 1. Text Selection: if the user highlighted text in textarea
    if (selStart !== undefined && selEnd !== undefined && selStart !== selEnd) {
      const selected = fullSql.substring(
        Math.min(selStart, selEnd),
        Math.max(selStart, selEnd)
      ).trim()
      if (selected) {
        return selected
      }
    }

    // 2. Cursor position: find the statement at or around cursor
    const cursorPos = selEnd !== undefined ? selEnd : (selStart !== undefined ? selStart : fullSql.length)
    const statements = parseSqlStatements(fullSql)

    if (statements.length === 0) {
      return fullSql.trim()
    }
    if (statements.length === 1) {
      return statements[0].sql
    }

    // Check if cursor is directly within statement range [stmt.start, stmt.end]
    for (let i = 0; i < statements.length; i++) {
      const stmt = statements[i]
      if (cursorPos >= stmt.start && cursorPos <= stmt.end) {
        return stmt.sql
      }
    }

    // If cursor is before first statement
    if (cursorPos < statements[0].start) {
      return statements[0].sql
    }

    // If cursor is at or after last statement
    if (cursorPos >= statements[statements.length - 1].end) {
      return statements[statements.length - 1].sql
    }

    // If cursor is in the gap between statements
    for (let i = 0; i < statements.length - 1; i++) {
      const curr = statements[i]
      const next = statements[i + 1]
      if (cursorPos > curr.end && cursorPos < next.start) {
        const gap = fullSql.substring(curr.end, cursorPos)
        return !gap.includes('\n') ? curr.sql : next.sql
      }
    }

    return statements[0].sql
  }

  const statements = parseSqlStatements(fullSql)
  return statements.length > 0 ? statements[0].sql : fullSql.trim()
}
