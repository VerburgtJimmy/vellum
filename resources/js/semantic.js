/**
 * The query side of a semantic file, in the browser: the same WordPiece
 * tokenisation PHP does, the same mean pooling, the same cosine. Loaded on
 * demand next to the search chunk; nothing here runs before someone searches.
 *
 * The file is written by vellum:build. Its layout is documented on
 * Vellum\Semantic\SemanticSet.
 */

const MAGIC = 0x4d455356 // "VSEM", little-endian
const FORMAT = 1
const MAX_WORD = 100

/**
 * Bert's normaliser: drop control characters, pad CJK, strip accents, lowercase.
 *
 * @param {string} text
 */
export function normalize(text) {
  return text
    .replace(/[\u0000�]|(?![\t\n\r])[\p{Cc}\p{Cf}]/gu, '')
    .replace(/[\t\n\r\p{Zs}]/gu, ' ')
    .replace(
      /([一-鿿㐀-䶿豈-﫿]|[\u{20000}-\u{2a6df}]|[\u{2a700}-\u{2b73f}]|[\u{2b740}-\u{2b81f}]|[\u{2b820}-\u{2ceaf}]|[\u{2f800}-\u{2fa1f}])/gu,
      ' $1 ',
    )
    .normalize('NFD')
    .replace(/\p{Mn}/gu, '')
    .toLowerCase()
}

/**
 * Bert's pre-tokeniser: whitespace splits, punctuation stands alone.
 *
 * @param {string} text
 * @returns {Array<string>}
 */
export function words(text) {
  return text.match(/[!-/:-@[-`{-~]|\p{P}|[^\s!-/:-@[-`{-~\p{P}]+/gu) ?? []
}

/**
 * WordPiece over the vocabulary the file carries: longest match first, a word
 * that cannot be covered becomes unknown and is skipped.
 *
 * @param {string} text
 * @param {Map<string, number>} vocab
 * @returns {Array<number>}
 */
export function tokenize(text, vocab) {
  const ids = []

  for (const word of words(normalize(text))) {
    const chars = [...word]

    if (chars.length > MAX_WORD) {
      continue
    }

    const pieces = []
    let start = 0

    while (start < chars.length) {
      let end = chars.length
      let id

      while (start < end) {
        const piece = (start > 0 ? '##' : '') + chars.slice(start, end).join('')
        id = vocab.get(piece)

        if (id !== undefined) {
          break
        }

        end--
      }

      if (id === undefined) {
        pieces.length = 0
        break
      }

      pieces.push(id)
      start = end
    }

    ids.push(...pieces)
  }

  return ids
}

/**
 * Read a semantic file into a vocabulary, section ids and their vectors.
 *
 * @param {ArrayBuffer} buffer
 */
export function readSemanticFile(buffer) {
  const view = new DataView(buffer)

  if (view.byteLength < 18 || view.getUint32(0, true) !== MAGIC) {
    throw new Error('Not a Vellum semantic file')
  }

  const format = view.getUint8(4)

  if (format !== FORMAT) {
    throw new Error(`Unsupported semantic file format ${format}`)
  }

  const vocabBits = view.getUint8(5)
  const sectionBits = view.getUint8(6)
  const dims = view.getUint16(8, true)
  const tokenCount = view.getUint32(10, true)
  const sectionCount = view.getUint32(14, true)
  const decoder = new TextDecoder()
  let offset = 18

  const blob = () => {
    const length = view.getUint32(offset, true)
    const text = decoder.decode(new Uint8Array(buffer, offset + 4, length))
    offset += 4 + length

    return text
  }

  const attribution = blob()
  const tokenBlob = blob()
  const idBlob = blob()
  const vocab = new Map()

  if (tokenCount > 0) {
    tokenBlob.split('\n').forEach((token, index) => vocab.set(token, index))
  }

  const rows = (count, bits) => {
    const width = 4 + (bits === 8 ? dims : Math.ceil(dims / 2))
    const vectors = []

    for (let i = 0; i < count; i++) {
      const at = offset + i * width
      const scale = view.getFloat32(at, true)
      const vector = new Float32Array(dims)

      for (let d = 0; d < dims; d++) {
        vector[d] =
          bits === 8
            ? view.getInt8(at + 4 + d) * scale
            : (((view.getUint8(at + 4 + (d >> 1)) >> (d % 2 === 0 ? 4 : 0)) & 0x0f) - 8) * scale
      }

      vectors.push(vector)
    }

    offset += count * width

    return vectors
  }

  const table = rows(tokenCount, vocabBits)
  const sections = rows(sectionCount, sectionBits).map(unit)

  return { attribution, dims, vocab, table, ids: sectionCount > 0 ? idBlob.split('\n') : [], sections }
}

/**
 * @param {Float32Array} vector
 */
function unit(vector) {
  let norm = 0

  for (const value of vector) {
    norm += value * value
  }

  norm = Math.sqrt(norm)

  if (norm > 0) {
    for (let i = 0; i < vector.length; i++) {
      vector[i] /= norm
    }
  }

  return vector
}

/**
 * Mean of a text's token vectors, normalised. Null when it has no known token.
 *
 * @param {string} text
 * @param {{vocab: Map<string, number>, table: Array<Float32Array>, dims: number}} file
 */
export function encode(text, file) {
  const ids = tokenize(text, file.vocab)

  if (ids.length === 0) {
    return null
  }

  const sum = new Float32Array(file.dims)

  for (const id of ids) {
    const row = file.table[id]

    for (let d = 0; d < file.dims; d++) {
      sum[d] += row[d]
    }
  }

  return unit(sum)
}

/**
 * Cosine of a query against every section, by section id.
 *
 * @param {string} query
 * @param {ReturnType<typeof readSemanticFile>} file
 * @returns {Map<string, number>}
 */
export function cosines(query, file) {
  const vector = encode(query, file)
  const scores = new Map()

  if (vector === null) {
    return scores
  }

  file.sections.forEach((section, index) => {
    let dot = 0

    for (let d = 0; d < file.dims; d++) {
      dot += vector[d] * section[d]
    }

    scores.set(file.ids[index], dot)
  })

  return scores
}

/**
 * @param {string} url
 */
export async function loadSemanticFile(url) {
  const response = await fetch(url, { credentials: 'same-origin' })

  if (!response.ok) {
    throw new Error(`Failed to load the semantic file (${response.status})`)
  }

  return readSemanticFile(await response.arrayBuffer())
}
