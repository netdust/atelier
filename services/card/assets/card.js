// The chooser's memory, and the card's worker, flip and Share. Every URL comes from the server's markup.
(() => {
  const KEY = 'atelier-card';
  // Storage throws in some private modes; the card then simply forgets.
  const remembered = {
    get: () => { try { return localStorage.getItem(KEY) || ''; } catch { return ''; } },
    set: (slug) => { try { localStorage.setItem(KEY, slug); } catch { /* forgets */ } },
    forget: () => { try { localStorage.removeItem(KEY); } catch { /* forgets */ } },
  };

  // /card/ lists exactly the cards that answer: a remembered slug missing from it is a card that 404s now.
  const chooser = document.getElementById('chooser');
  if (chooser) {
    const links = [...chooser.querySelectorAll('a[data-slug]')];
    const chosen = links.find((link) => link.dataset.slug === remembered.get());
    if (chosen) {
      window.location.replace(chosen.href);
      return;
    }
    remembered.forget();
    links.forEach((link) => link.addEventListener('click', () => remembered.set(link.dataset.slug)));
    return;
  }

  const card = document.getElementById('card');
  if (!card) return;

  const $ = (id) => document.getElementById(id);

  // The route sends Service-Worker-Allowed for this scope; the option is what claims it.
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(card.dataset.worker, { scope: card.dataset.scope }).catch(() => {});
  }

  // Only on the phone that chose this card: anyone the card was shared with has nothing to undo.
  const notYou = $('not-you');
  if (notYou && remembered.get() === card.dataset.slug) {
    notYou.hidden = false;
    notYou.addEventListener('click', () => remembered.forget());
  }

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
