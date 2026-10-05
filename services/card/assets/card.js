// The card's flip and Share. What is shared comes from the server's data- attributes.
(() => {
  const card = document.getElementById('card');
  if (!card) return;

  const $ = (id) => document.getElementById(id);
  const { shareUrl, shareTitle, copied } = card.dataset;
  const themeColor = document.querySelector('meta[name="theme-color"]');
  const paper = themeColor ? themeColor.content : '';

  function flip(open) {
    card.classList.toggle('is-back', open);
    $('flip').setAttribute('aria-expanded', String(open));
    $('back').setAttribute('aria-hidden', String(!open));
    if (themeColor) themeColor.content = open ? '#111110' : paper;
    (open ? $('unflip') : $('flip')).focus({ preventScroll: true });
  }

  $('flip').addEventListener('click', () => flip(true));
  $('unflip').addEventListener('click', () => flip(false));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && card.classList.contains('is-back')) flip(false);
  });

  const shareButton = $('share');

  function note(text) {
    shareButton.dataset.note = text;
    setTimeout(() => delete shareButton.dataset.note, 2000);
  }

  function copyLink() {
    navigator.clipboard
      .writeText(shareUrl)
      .then(() => note(copied))
      .catch(() => {});
  }

  // A URL is the one shape every share sheet takes.
  shareButton.addEventListener('click', () => {
    if (!navigator.share) {
      copyLink();
      return;
    }
    navigator.share({ title: shareTitle, url: shareUrl }).catch((error) => {
      if (error.name !== 'AbortError') copyLink();
    });
  });
})();
