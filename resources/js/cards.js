/**
 * The hover highlight on a group of cards glides from card to card, like the
 * sidebar's, instead of each card lighting up on its own. Without this
 * script each card still highlights on hover.
 */
export function initCards() {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches

  document.querySelectorAll('[data-vellum-cards]').forEach((grid) => {
    const glide = document.createElement('span')
    glide.className = 'vellum-cards-glide'
    glide.setAttribute('aria-hidden', 'true')
    grid.prepend(glide)
    grid.setAttribute('data-vellum-cards-gliding', '')

    grid.addEventListener('pointerover', (event) => {
      const card = event.target instanceof Element ? event.target.closest('[data-vellum-card]') : null

      if (!card || card.parentElement !== grid) {
        return
      }

      glide.toggleAttribute('data-gliding', glide.hasAttribute('data-shown') && !reduced)
      glide.style.transform = `translate(${card.offsetLeft}px, ${card.offsetTop}px)`
      glide.style.width = `${card.offsetWidth}px`
      glide.style.height = `${card.offsetHeight}px`
      glide.setAttribute('data-shown', '')
    })

    grid.addEventListener('pointerleave', () => {
      glide.removeAttribute('data-shown')
      glide.removeAttribute('data-gliding')
    })
  })
}
