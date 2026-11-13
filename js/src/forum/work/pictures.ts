export interface Picture {
  /** What the stage shows: the full picture where a link holds it. */
  src: string;
  /** What a thumbnail shows: the smaller copy when there is one. */
  thumb: string;
  alt: string;
  /** Height per 1000 of width, 0 when the markup doesn't say. */
  ratio: number;
}

const memo = new Map<string, Picture[]>();

/**
 * The pictures in a post, from its rendered HTML, in order: what the reader
 * would see scrolling the post. Emoji and anything inside a spoiler are left
 * out, as on the server.
 *
 * A fof/upload picture is a thumbnail wrapped in a link to the full file; the
 * stage shows the full one, the strip the thumbnail.
 */
export function picturesIn(html: string | null | undefined): Picture[] {
  if (!html || !/<img\b/i.test(html)) return [];

  const known = memo.get(html);

  if (known) return known;

  const doc = new DOMParser().parseFromString(html, 'text/html');
  const out: Picture[] = [];

  doc.querySelectorAll('img').forEach((img) => {
    if (img.classList.contains('emoji') || img.closest('details, .spoiler, [class*="Spoiler"], [data-s9e-mediaembed]')) return;

    const thumb = img.getAttribute('src') || '';

    if (!/^(https?:\/\/|\/(?!\/))/i.test(thumb)) return;

    const link = img.closest('a');
    const href = link?.getAttribute('href') || '';
    const full = /\.(avif|gif|jpe?g|png|webp)(\?|#|$)/i.test(href) || link?.classList.contains('FoFUpload--Upl-Image-Preview-Link') ? href : thumb;
    const width = parseInt(img.getAttribute('width') || '', 10);
    const height = parseInt(img.getAttribute('height') || '', 10);

    out.push({
      src: /^(https?:\/\/|\/(?!\/))/i.test(full) ? full : thumb,
      thumb,
      alt: img.getAttribute('alt') || '',
      ratio: width > 0 && height > 0 ? Math.round((height * 1000) / width) : 0,
    });
  });

  // Posts are few; keep the memo from growing with a long session.
  if (memo.size > 50) memo.clear();
  memo.set(html, out);

  return out;
}
